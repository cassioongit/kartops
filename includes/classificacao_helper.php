<?php
/**
 * Helper para Cálculos de Classificação
 * Fonte única de verdade — usada pela API e pela Home
 */

require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);

function getClassificationData($pdo, $categoryFilter = null)
{
    // 1. Etapas válidas
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data
        FROM etapas
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Resultados
    $sql = "
        SELECT r.piloto_id, r.equipe_id, r.etapa_id, r.pontos, r.categoria, r.posicao,
               COALESCE(r.pontos_penalidade, 0) as pontos_penalidade,
               COALESCE(r.desclassificado, 0) as desclassificado,
               p.nome as piloto_nome, p.apelido as piloto_apelido, p.foto as piloto_foto,
               e.nome as equipe_nome, e.imagem as equipe_imagem, e.cor as equipe_cor,
               et.data as etapa_data
        FROM resultados r
        LEFT JOIN pilotos p ON r.piloto_id = p.id
        LEFT JOIN equipes e ON r.equipe_id = e.id
        LEFT JOIN etapas et ON r.etapa_id = et.id
        WHERE 1=1
    ";

    $params = [];
    if ($categoryFilter) {
        if (stripos($categoryFilter, 'Challenger') !== false || stripos($categoryFilter, 'Challenge') !== false) {
            $sql .= " AND (r.categoria LIKE 'Challenger%' OR r.categoria LIKE 'Challenge%')";
        } else {
            $sql .= " AND r.categoria = ?";
            $params[] = $categoryFilter;
        }
    }
    $sql .= " ORDER BY et.data ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Processar resultados existentes no banco
    $classificacao = [];

    foreach ($resultados as $row) {
        $pid = $row['piloto_id'];
        if (!$pid) continue;

        if (!isset($classificacao[$pid])) {
            $classificacao[$pid] = [
                'id'               => $pid,
                'nome'             => $row['piloto_apelido'] ?: $row['piloto_nome'],
                'foto'             => $row['piloto_foto'] ?: 'images/turtle-driver.png',
                'equipe_id'        => $row['equipe_id'],
                'equipe'           => $row['equipe_nome'],
                'equipe_img'       => $row['equipe_imagem'],
                'equipe_cor'       => $row['equipe_cor'],
                'pontos_por_etapa' => [],
                'total'            => 0,
                'vitorias'         => 0,
                'descartes'        => 0,
                'descartes_etapas' => [],
                'participacoes'    => 0,
                'posicao_por_etapa'=> [],
                'posicoes'         => [],
                'num_adv'          => 0,
                'candidatos_descarte' => []
            ];
            // null = ausente (distinto de 0 = DSQ ou falta registrada)
            foreach ($etapas as $et) {
                $classificacao[$pid]['pontos_por_etapa'][$et['id']] = null;
            }
        }

        // pontos já contém o valor final (base − penalidades) via recalcularPontosEtapa
        $pontos_final    = (float)($row['pontos'] ?? 0);
        $desclassificado = (bool)($row['desclassificado'] ?? false);
        if ($desclassificado) $pontos_final = 0;

        $classificacao[$pid]['pontos_por_etapa'][$row['etapa_id']] = $pontos_final;
        $classificacao[$pid]['total'] += $pontos_final;

        // Participação = largou (posicao > 0); posicao=0 = DNS/falta registrada
        if ($row['posicao'] > 0) {
            $classificacao[$pid]['participacoes']++;
        }

        // Posição para desempate C e F (somente se não DSQ e largou)
        if ($row['posicao'] > 0 && !$desclassificado) {
            $pos = (int)$row['posicao'];
            $classificacao[$pid]['posicoes'][$pos] = ($classificacao[$pid]['posicoes'][$pos] ?? 0) + 1;
            $classificacao[$pid]['posicao_por_etapa'][$row['etapa_id']] = $pos;
        }

        // DSQ não entra em descarte; falta (posicao=0, desclassificado=false) entra como 0 pts
        if (!$desclassificado) {
            $classificacao[$pid]['candidatos_descarte'][$row['etapa_id']] = $pontos_final;
        }

        if ($row['posicao'] == 1 && !$desclassificado) {
            $classificacao[$pid]['vitorias']++;
        }
    }

    // 3.5 Ausências sem registro = falta = 0 pts descartáveis
    // Para etapas que TIVERAM resultados de outros pilotos (etapa já realizada),
    // pilotos sem nenhum registro tratam a ausência como 0 pts elegível a descarte.
    $etapasRealizadasSet = array_flip(array_unique(array_column($resultados, 'etapa_id')));
    foreach ($classificacao as $pid => &$pilot) {
        foreach ($etapas as $et) {
            $etid = $et['id'];
            if (isset($etapasRealizadasSet[$etid])
                && $pilot['pontos_por_etapa'][$etid] === null
                && !isset($pilot['candidatos_descarte'][$etid])) {
                // Etapa realizada, piloto sem registro = ausência = entra em descarte como 0
                $pilot['candidatos_descarte'][$etid] = 0;
            }
        }
    }
    unset($pilot);

    // 4. Descartes — 5 etapas → 1 descarte | 9 etapas → 2 descartes
    $totalEtapas    = count($etapas);
    $limiteDescartes = 0;
    if ($totalEtapas >= 9)      $limiteDescartes = 2;
    elseif ($totalEtapas >= 5)  $limiteDescartes = 1;

    if ($limiteDescartes > 0) {
        foreach ($classificacao as &$pilot) {
            if (!empty($pilot['candidatos_descarte'])) {
                asort($pilot['candidatos_descarte']);
                $descartados = array_slice($pilot['candidatos_descarte'], 0, $limiteDescartes, true);
                $pilot['total']           -= array_sum($descartados);
                $pilot['descartes']        = array_sum($descartados);
                $pilot['descartes_etapas'] = array_keys($descartados);
            }
        }
        unset($pilot);
    }

    // 5. Advertências (critério B de desempate)
    $stmtAdv = $pdo->query("
        SELECT pen.piloto_id, COUNT(*) as num_adv
        FROM penalidades pen
        JOIN tipos_penalidade tp ON pen.tipo_penalidade_id = tp.id
        WHERE tp.codigo = 'advertencia'
        GROUP BY pen.piloto_id
    ");
    $advMap = [];
    while ($advRow = $stmtAdv->fetch()) {
        $advMap[$advRow['piloto_id']] = (int)$advRow['num_adv'];
    }
    foreach ($classificacao as $pid => &$pilot) {
        $pilot['num_adv'] = $advMap[$pid] ?? 0;
    }
    unset($pilot);

    // 6. Ordenar — desempate §5.4 OsKarteiro 2026
    usort($classificacao, function ($a, $b) {
        $diff = $b['total'] <=> $a['total'];
        if ($diff !== 0) return $diff;

        // A. Maior nº de participações (posicao > 0)
        $diff = ($b['participacoes'] ?? 0) <=> ($a['participacoes'] ?? 0);
        if ($diff !== 0) return $diff;

        // B. Menor nº de advertências
        $diff = ($a['num_adv'] ?? 0) <=> ($b['num_adv'] ?? 0);
        if ($diff !== 0) return $diff;

        // C. Melhor soma de colocações (equalizada, sem descartadas nem ausências)
        $desA = array_flip($a['descartes_etapas'] ?? []);
        $desB = array_flip($b['descartes_etapas'] ?? []);
        $posA = array_values(array_filter(
            $a['posicao_por_etapa'] ?? [],
            fn($etid) => !isset($desA[$etid]),
            ARRAY_FILTER_USE_KEY
        ));
        $posB = array_values(array_filter(
            $b['posicao_por_etapa'] ?? [],
            fn($etid) => !isset($desB[$etid]),
            ARRAY_FILTER_USE_KEY
        ));
        sort($posA);
        sort($posB);
        $n = min(count($posA), count($posB));
        if ($n > 0) {
            $diff = array_sum(array_slice($posA, 0, $n)) <=> array_sum(array_slice($posB, 0, $n));
            if ($diff !== 0) return $diff;
        }

        // D. Maior nº de vitórias
        $diff = ($b['vitorias'] ?? 0) <=> ($a['vitorias'] ?? 0);
        if ($diff !== 0) return $diff;

        // F. Maior nº de 2ºs, 3ºs...
        $maxPos = max(
            empty($a['posicoes']) ? 1 : max(array_keys($a['posicoes'])),
            empty($b['posicoes']) ? 1 : max(array_keys($b['posicoes']))
        );
        for ($pos = 2; $pos <= $maxPos; $pos++) {
            $diff = ($b['posicoes'][$pos] ?? 0) <=> ($a['posicoes'][$pos] ?? 0);
            if ($diff !== 0) return $diff;
        }

        return 0; // G. Sorteio
    });

    // 7. Posição e anotação do critério de desempate
    $classificacao = array_values($classificacao);
    foreach ($classificacao as $k => &$item) {
        $item['pos']             = $k + 1;
        $item['tiebreaker_used'] = null;

        if ($k > 0) {
            $prev = $classificacao[$k - 1];
            if ((float)$item['total'] === (float)$prev['total']) {
                if (($item['participacoes'] ?? 0) !== ($prev['participacoes'] ?? 0)) {
                    $item['tiebreaker_used'] = 'A';
                } elseif (($item['num_adv'] ?? 0) !== ($prev['num_adv'] ?? 0)) {
                    $item['tiebreaker_used'] = 'B';
                } elseif ((function () use ($item, $prev) {
                    $desI = array_flip($item['descartes_etapas'] ?? []);
                    $desP = array_flip($prev['descartes_etapas'] ?? []);
                    $pI = array_values(array_filter($item['posicao_por_etapa'] ?? [], fn($k) => !isset($desI[$k]), ARRAY_FILTER_USE_KEY));
                    $pP = array_values(array_filter($prev['posicao_por_etapa'] ?? [], fn($k) => !isset($desP[$k]), ARRAY_FILTER_USE_KEY));
                    sort($pI); sort($pP);
                    $n = min(count($pI), count($pP));
                    return $n > 0 && array_sum(array_slice($pI, 0, $n)) !== array_sum(array_slice($pP, 0, $n));
                })()) {
                    $item['tiebreaker_used'] = 'C';
                } elseif (($item['vitorias'] ?? 0) !== ($prev['vitorias'] ?? 0)) {
                    $item['tiebreaker_used'] = 'D';
                } else {
                    $maxPos = max(
                        empty($item['posicoes']) ? 1 : max(array_keys($item['posicoes'])),
                        empty($prev['posicoes']) ? 1 : max(array_keys($prev['posicoes']))
                    );
                    for ($pos = 2; $pos <= $maxPos; $pos++) {
                        if (($item['posicoes'][$pos] ?? 0) !== ($prev['posicoes'][$pos] ?? 0)) {
                            $item['tiebreaker_used'] = 'F';
                            break;
                        }
                    }
                    if (!$item['tiebreaker_used']) {
                        $item['tiebreaker_used'] = 'G';
                    }
                }
            }
        }
    }
    unset($item);

    return $classificacao;
}

/**
 * Classificação de equipes com substituição dinâmica por etapa.
 *
 * Para cada etapa: os 2 melhores resultados VÁLIDOS (não descartados) da equipe
 * naquela categoria são somados. Se um resultado foi descartado, o próximo piloto
 * da equipe naquela etapa assume o lugar — sem distorções entre descartes individuais
 * e pontuação coletiva.
 */
function getTeamClassificationData($pdo, $categoryFilter = null)
{
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data
        FROM etapas
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    $masterPilotos     = getClassificationData($pdo, 'Master');
    $challengerPilotos = getClassificationData($pdo, 'Challengers');

    // Agrupa TODOS os pilotos por equipe (ordem do melhor ao pior pelo total)
    $byTeam = [];

    foreach ($masterPilotos as $p) {
        $tid = $p['equipe_id'];
        if (!$tid) continue;
        if (!isset($byTeam[$tid])) $byTeam[$tid] = _teamShell($p);
        $byTeam[$tid]['_master'][] = $p;
    }
    foreach ($challengerPilotos as $p) {
        $tid = $p['equipe_id'];
        if (!$tid) continue;
        if (!isset($byTeam[$tid])) $byTeam[$tid] = _teamShell($p);
        $byTeam[$tid]['_challengers'][] = $p;
    }

    // Calcula totais por etapa (top 2 válidos por etapa, com substituição)
    foreach ($byTeam as $tid => &$team) {
        $totalMaster      = 0.0;
        $totalChallengers = 0.0;
        $pontosPorEtapaMaster      = [];
        $pontosPorEtapaChallengers = [];

        foreach ($etapas as $et) {
            $etid = $et['id'];

            $mPts = _top2EtapaValidos($team['_master'], $etid);
            $cPts = _top2EtapaValidos($team['_challengers'], $etid);

            $totalMaster      += $mPts;
            $totalChallengers += $cPts;
            $pontosPorEtapaMaster[$etid]      = $mPts;
            $pontosPorEtapaChallengers[$etid] = $cPts;
        }

        $team['total_master']                 = $totalMaster;
        $team['total_challengers']            = $totalChallengers;
        $team['total']                        = $totalMaster + $totalChallengers;
        $team['pontos_por_etapa_master']      = $pontosPorEtapaMaster;
        $team['pontos_por_etapa_challengers'] = $pontosPorEtapaChallengers;

        // Listas para exibição — todos os pilotos da equipe na categoria
        $team['pilotos_master'] = array_map(fn($p) => [
            'id'      => $p['id'],
            'nome'    => $p['nome'],
            'foto'    => $p['foto'],
            'vitorias'=> $p['vitorias'] ?? 0,
        ], $team['_master']);

        $team['pilotos_challengers'] = array_map(fn($p) => [
            'id'      => $p['id'],
            'nome'    => $p['nome'],
            'foto'    => $p['foto'],
            'vitorias'=> $p['vitorias'] ?? 0,
        ], $team['_challengers']);

        unset($team['_master'], $team['_challengers']);
    }
    unset($team);

    // Filtro de categoria para exibição
    if ($categoryFilter) {
        foreach ($byTeam as &$team) {
            if (strcasecmp($categoryFilter, 'Master') === 0) {
                $team['total'] = $team['total_master'];
            } elseif (stripos($categoryFilter, 'Challenger') !== false) {
                $team['total'] = $team['total_challengers'];
            }
        }
        unset($team);
    }

    usort($byTeam, fn($a, $b) => $b['total'] <=> $a['total']);

    $byTeam = array_values($byTeam);
    foreach ($byTeam as $k => &$team) {
        $team['pos'] = $k + 1;
    }
    unset($team);

    return $byTeam;
}

/**
 * Soma os pontos dos top 2 pilotos VÁLIDOS de uma lista para uma etapa específica.
 * "Válido" = tem resultado para a etapa (pontos_por_etapa !== null)
 *            E o resultado NÃO foi descartado.
 * Se o 1º piloto foi descartado nesta etapa, o 3º entra em seu lugar, etc.
 */
function _top2EtapaValidos(array $pilotos, $etapaId): float
{
    $pontosValidos = [];
    foreach ($pilotos as $p) {
        $pts = $p['pontos_por_etapa'][$etapaId] ?? null;
        if ($pts === null) continue; // ausente sem registro
        $descartado = in_array($etapaId, $p['descartes_etapas'] ?? []);
        if ($descartado) continue;   // resultado descartado não conta para a equipe
        $pontosValidos[] = (float)$pts;
    }
    rsort($pontosValidos);
    return array_sum(array_slice($pontosValidos, 0, 2));
}

/** Inicializa a estrutura base de uma equipe */
function _teamShell(array $pilot): array
{
    return [
        'id'                 => $pilot['equipe_id'],
        'nome'               => $pilot['equipe'],
        'foto'               => $pilot['equipe_img'],
        'cor'                => $pilot['equipe_cor'] ?? '#1c1e32',
        'total'              => 0,
        'total_master'       => 0,
        'total_challengers'  => 0,
        'pilotos_master'     => [],
        'pilotos_challengers'=> [],
        '_master'            => [],
        '_challengers'       => [],
    ];
}
