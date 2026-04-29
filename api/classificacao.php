<?php
/**
 * API CLASSIFICAÇÃO - KartOps
 * Retorna dados para a tabela de classificação (Pilotos e Equipes)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

$tipo = $_GET['tipo'] ?? 'pilotos'; // 'pilotos' ou 'equipes'
$categoria = $_GET['categoria'] ?? ''; // Filtro opcional

try {
    $pdo = getDBConnection();

    // 1. Buscar todas as Etapas (Realizadas ou próximos 8 dias)
    // Ordenadas por Data para garantir numeração correta (ET1, ET2...)
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data 
        FROM etapas 
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Buscar Resultados
    // Precisamos de todos os resultados para somar (considerando penalidades)
    $sql = "
        SELECT r.piloto_id, r.equipe_id, r.etapa_id, r.pontos, r.categoria, r.posicao,
               COALESCE(r.pontos_penalidade, 0) as pontos_penalidade,
               COALESCE(r.desclassificado, 0) as desclassificado,
               p.nome as piloto_nome, p.apelido as piloto_apelido, p.foto as piloto_foto,
               e.nome as equipe_nome, e.imagem as equipe_imagem, e.cor as equipe_cor
        FROM resultados r
        LEFT JOIN pilotos p ON r.piloto_id = p.id
        LEFT JOIN equipes e ON r.equipe_id = e.id
        LEFT JOIN etapas et ON r.etapa_id = et.id
        WHERE 1=1
    ";

    $params = [];
    if ($categoria && $tipo === 'pilotos') {
        // Filtrar por categoria
        // Regra especial: "Challengers" agrupa todas as variações (Challenger I, II, III, Challenge, etc)
        // O usuário pediu que a classificação seja unificada para Challengers.
        if (stripos($categoria, 'Challenger') !== false || stripos($categoria, 'Challenge') !== false) {
            $sql .= " AND (r.categoria LIKE 'Challenger%' OR r.categoria LIKE 'Challenge%')";
        } else {
            $sql .= " AND r.categoria = ?";
            $params[] = $categoria;
        }
    }

    // Ordenar para processamento
    $sql .= " ORDER BY et.data ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Processar Dados
    $classificacao = [];

    if ($tipo === 'pilotos') {
        foreach ($resultados as $row) {
            $pid = $row['piloto_id'];
            if (!$pid)
                continue;

            if (!isset($classificacao[$pid])) {
                $classificacao[$pid] = [
                    'id' => $pid,
                    'nome' => $row['piloto_apelido'] ?: $row['piloto_nome'],
                    'foto' => $row['piloto_foto'] ?: 'images/turtle-driver.png',
                    'equipe' => $row['equipe_nome'],
                    'equipe_img' => $row['equipe_imagem'],
                    'equipe_cor' => $row['equipe_cor'], // Adicionado cor da equipe
                    'pontos_por_etapa' => [],
                    'total' => 0,
                    'vitorias' => 0,
                    'descartes' => 0
                ];
                // Inicializar etapas com 0 ou null? Melhor null para diferenciar "não correu" de "0 pts"
                foreach ($etapas as $et) {
                    $classificacao[$pid]['pontos_por_etapa'][$et['id']] = 0; // Ou null
                }
            }

            // Somar pontos (se piloto correu em 2 categorias na mesma etapa? 
            // Geralmente pega o melhor ou soma? 
            // O filtro Categoria já resolve. Se não tiver filtro, soma tudo (ranking geral).

            // Adicionar pontos na etapa (considerando penalidades)
            // Nota: Se houver duplicidade de etapa_id buga? Sim. 
            // O constraint UNIQUE piloto_etapa criado anteriormente evita duplicidade.

            // Calcular pontos finais (pontos - penalidades, ou 0 se desclassificado)
            $pontos_brutos = (float) $row['pontos'];
            $pontos_penalidade = (float) ($row['pontos_penalidade'] ?? 0);
            $desclassificado = (bool) ($row['desclassificado'] ?? false);

            // O campo 'pontos' no banco agora já contém o valor final (base - penalidades)
            // mantido pelas funções recalcularPontosEtapa e recalcularPontosResultado.
            $pontos_final = (float)($row['pontos'] ?? 0);
            
            // Garantir que se estiver desclassificado seja 0 (backup)
            if ($desclassificado) $pontos_final = 0;

            $classificacao[$pid]['pontos_por_etapa'][$row['etapa_id']] = $pontos_final;
            $classificacao[$pid]['total'] += $pontos_final;

            // Coletar pontos para possível descarte (Não descarta DSQ)
            if (!$desclassificado) {
                if (!isset($classificacao[$pid]['candidatos_descarte'])) $classificacao[$pid]['candidatos_descarte'] = [];
                $classificacao[$pid]['candidatos_descarte'][] = $pontos_final;
            }

            // Contabilizar Vitórias (Posição 1 e não desclassificado)
            if ($row['posicao'] == 1 && !$desclassificado) {
                $classificacao[$pid]['vitorias']++;
            }
        }

        // --- APLICAR REGRA DE DESCARTE (Pilotos) ---
        // 6 etapas -> 1 descarte | 9 etapas -> 2 descartes
        $totalEtapasRealizadas = count($etapas); // Considerando as etapas retornadas pela API
        $limiteDescartes = 0;
        if ($totalEtapasRealizadas >= 9) $limiteDescartes = 2;
        elseif ($totalEtapasRealizadas >= 6) $limiteDescartes = 1;

        if ($limiteDescartes > 0) {
            foreach ($classificacao as $pid => &$pilot) {
                if (!empty($pilot['candidatos_descarte'])) {
                    // Ordenar do menor para o maior
                    sort($pilot['candidatos_descarte']);
                    
                    // Pegar os X piores
                    $descartados = array_slice($pilot['candidatos_descarte'], 0, $limiteDescartes);
                    $somaDescarte = array_sum($descartados);
                    
                    // Subtrair do total
                    $pilot['total'] -= $somaDescarte;
                    $pilot['descartes'] = $somaDescarte;
                    $pilot['num_descartes'] = count($descartados);
                }
            }
        }
    } elseif ($tipo === 'equipes') {
        // Estrutura temporária para agrupar resultados: [team_id][etapa_id][grupo_categoria] => [pontos1, pontos2, ...]
        $teamStageResults = [];

        foreach ($resultados as $row) {
            $eid = $row['equipe_id'];
            if (!$eid)
                continue;

            // Inicializar estrutura da equipe na classificação final se não existir
            if (!isset($classificacao[$eid])) {
                $classificacao[$eid] = [
                    'id' => $eid,
                    'nome' => $row['equipe_nome'],
                    'foto' => $row['equipe_imagem'],
                    'cor' => $row['equipe_cor'],
                    'pontos_por_etapa' => [],
                    'total' => 0
                ];
                foreach ($etapas as $et) {
                    $classificacao[$eid]['pontos_por_etapa'][$et['id']] = 0;
                }
            }

            // Calcular pontos individuais
            $pontos_brutos = (float) $row['pontos'];
            $pontos_penalidade = (float) ($row['pontos_penalidade'] ?? 0);
            $desclassificado = (bool) ($row['desclassificado'] ?? false);
            // O campo 'pontos' no banco agora já contém o valor final (base - penalidades)
            $pontos_final = (float)($row['pontos'] ?? 0);
            
            // Garantir que se estiver desclassificado seja 0 (backup)
            if ($desclassificado) $pontos_final = 0;

            // Determinar grupo (Master ou Challengers)
            $catGroup = 'Outros';
            $catRaw = $row['categoria'];
            if ($catRaw === 'Master') {
                $catGroup = 'Master';
            } elseif (stripos($catRaw, 'Challenge') !== false) {
                $catGroup = 'Challengers';
            }

            // Agrupar
            $teamStageResults[$eid][$row['etapa_id']][$catGroup][] = $pontos_final;
        }

        // Processar soma 
        foreach ($teamStageResults as $eid => $etapasData) {
            foreach ($etapasData as $etapaId => $groups) {
                // Processar Master
                $somaMasterEtapa = 0;
                if (isset($groups['Master'])) {
                    rsort($groups['Master']); // Decrescente
                    $top2Master = array_slice($groups['Master'], 0, 2);
                    $somaMasterEtapa = array_sum($top2Master);
                }

                // Processar Challengers
                $somaChallengersEtapa = 0;
                if (isset($groups['Challengers'])) {
                    rsort($groups['Challengers']); // Decrescente
                    $top2Challengers = array_slice($groups['Challengers'], 0, 2);
                    $somaChallengersEtapa = array_sum($top2Challengers);
                }

                $somaTotalEtapa = $somaMasterEtapa + $somaChallengersEtapa;

                // Atualizar dados finais
                if (isset($classificacao[$eid])) {
                    // Se estivermos filtrando, mostramos apenas os pontos da categoria filtrada na etapa?
                    // Ou o total daquela categoria.

                    // Armazena estruturado para flexibilidade
                    if (!isset($classificacao[$eid]['total_master']))
                        $classificacao[$eid]['total_master'] = 0;
                    if (!isset($classificacao[$eid]['total_challengers']))
                        $classificacao[$eid]['total_challengers'] = 0;

                    $classificacao[$eid]['total_master'] += $somaMasterEtapa;
                    $classificacao[$eid]['total_challengers'] += $somaChallengersEtapa;
                    $classificacao[$eid]['total'] += $somaTotalEtapa;

                    // O pontos_por_etapa depende do que queremos exibir no gráfico.
                    // Se filtrado, mostra da categoria. Se geral, mostra soma.
                    if (strcasecmp($categoria, 'Master') === 0) {
                        $classificacao[$eid]['pontos_por_etapa'][$etapaId] = $somaMasterEtapa;
                    } elseif (stripos($categoria, 'Challenger') !== false) {
                        $classificacao[$eid]['pontos_por_etapa'][$etapaId] = $somaChallengersEtapa;
                    } else {
                        $classificacao[$eid]['pontos_por_etapa'][$etapaId] = $somaTotalEtapa;
                    }
                }
            }
        }
    }

    // 3.5 Ajuste Final do Total para Exibição (Se filtrado)
    if ($tipo === 'equipes') {
        foreach ($classificacao as &$team) {
            if (strcasecmp($categoria, 'Master') === 0) {
                $team['total'] = $team['total_master'] ?? 0;
            } elseif (stripos($categoria, 'Challenger') !== false) {
                $team['total'] = $team['total_challengers'] ?? 0;
            }
            // Se "" (Geral/Global no backend) mantém o total soma
        }
    }

    // 4. Ordenar Classificação
    usort($classificacao, function ($a, $b) use ($tipo, $categoria) {
        if ($tipo === 'equipes') {
            // Se filtro Master
            if (strcasecmp($categoria, 'Master') === 0) {
                return ($b['total_master'] ?? 0) <=> ($a['total_master'] ?? 0);
            }
            // Se filtro Challengers
            elseif (stripos($categoria, 'Challenger') !== false) {
                return ($b['total_challengers'] ?? 0) <=> ($a['total_challengers'] ?? 0);
            }
            // Geral (Soma das duas, "da menor para a maior"? O usuário disse isso, mas pontuação é maior melhor)
            // Assumindo DECRESCENTE (Maior pontuação primeiro) pois é campeonato
            return $b['total'] <=> $a['total'];
        }

        return $b['total'] <=> $a['total']; // Descrescente Padrão Pilotos
    });

    // Reindexar array para JSON (remove chaves de IDs)
    $classificacao = array_values($classificacao);

    // Adicionar Posição
    foreach ($classificacao as $k => &$item) {
        $item['pos'] = $k + 1;
    }

    echo json_encode([
        'success' => true,
        'etapas' => $etapas,
        'data' => $classificacao,
        'tipo' => $tipo
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
