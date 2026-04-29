<?php
/**
 * Helper para Cálculos de Classificação
 * Centraliza a lógica para ser usada na API e na Home
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);
function getClassificationData($pdo, $categoryFilter = null)
{
    // 1. Buscar Etapas válidas (Realizadas ou próximos 8 dias)
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data 
        FROM etapas 
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Buscar Resultados
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

    // 3. Processar
    $classificacao = [];

    foreach ($resultados as $row) {
        $pid = $row['piloto_id'];
        if (!$pid)
            continue;

        if (!isset($classificacao[$pid])) {
            $classificacao[$pid] = [
                'id' => $pid,
                'nome' => $row['piloto_apelido'] ?: $row['piloto_nome'],
                'foto' => $row['piloto_foto'] ?: 'images/turtle-driver.png',
                'equipe_id' => $row['equipe_id'],
                'equipe' => $row['equipe_nome'],
                'equipe_img' => $row['equipe_imagem'],
                'equipe_cor' => $row['equipe_cor'],
                'total' => 0,
                'vitorias' => 0
            ];
        }

        $pontos_brutos = (float) $row['pontos'];
        $pontos_penalidade = (float) ($row['pontos_penalidade'] ?? 0);
        $desclassificado = (bool) ($row['desclassificado'] ?? false);
        $pontos_final = $desclassificado ? 0 : max(0, $pontos_brutos + $pontos_penalidade);

        $classificacao[$pid]['total'] += $pontos_final;

        // Contabilizar Vitórias (Posição 1 e não desclassificado)
        if ($row['posicao'] == 1 && !$desclassificado) {
            $classificacao[$pid]['vitorias'] = ($classificacao[$pid]['vitorias'] ?? 0) + 1;
        }
    }

    // 4. Ordenar
    usort($classificacao, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // 5. Adicionar Posição
    $classificacao = array_values($classificacao);
    foreach ($classificacao as $k => &$item) {
        $item['pos'] = $k + 1;
    }

    return $classificacao;
}

function getTeamClassificationData($pdo, $categoryFilter = null)
{
    // 1. Buscar Etapas válidas
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data 
        FROM etapas 
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Buscar Resultados via CTE (Common Table Expression) e Window Functions
    $sql = "
        WITH RawResults AS (
            SELECT r.piloto_id, r.equipe_id, r.etapa_id, r.pontos, r.categoria, r.posicao,
                   COALESCE(r.pontos_penalidade, 0) as pontos_penalidade,
                   COALESCE(r.desclassificado, 0) as desclassificado,
                   p.nome as piloto_nome, p.apelido as piloto_apelido, p.foto as piloto_foto,
                   e.nome as equipe_nome, e.imagem as equipe_imagem, e.cor as equipe_cor,
                   CASE 
                       WHEN r.desclassificado THEN 0 
                       ELSE GREATEST(0, r.pontos + COALESCE(r.pontos_penalidade, 0)) 
                   END as pontos_final,
                   CASE 
                       WHEN r.categoria = 'Master' THEN 'Master'
                       WHEN r.categoria LIKE 'Challenge%' THEN 'Challengers'
                       ELSE 'Outros'
                   END as cat_group
            FROM resultados r
            LEFT JOIN pilotos p ON r.piloto_id = p.id
            LEFT JOIN equipes e ON r.equipe_id = e.id
            WHERE r.equipe_id IS NOT NULL AND r.equipe_id > 0
        )
        SELECT 
            *,
            ROW_NUMBER() OVER (
                PARTITION BY equipe_id, etapa_id, cat_group 
                ORDER BY pontos_final DESC, piloto_id ASC
            ) as cat_rank
        FROM RawResults
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Processar Dados
    $classificacao = [];

    foreach ($resultados as $row) {
        $eid = $row['equipe_id'];

        if (!isset($classificacao[$eid])) {
            $classificacao[$eid] = [
                'id' => $eid,
                'nome' => $row['equipe_nome'],
                'foto' => $row['equipe_imagem'],
                'cor' => $row['equipe_cor'],
                'total' => 0,
                'total_master' => 0,
                'total_challengers' => 0,
                'pilotos_master' => [], // Lista de IDs de pilotos que pontuaram na master
                'pilotos_challengers' => [] // Lista de IDs de pilotos que pontuaram na challengers
            ];
        }

        // Armazenar info do piloto para exibir depois
        $pilotoInfo = [
            'id' => $row['piloto_id'],
            'nome' => $row['piloto_apelido'] ?: $row['piloto_nome'],
            'foto' => $row['piloto_foto'] ?: 'images/turtle-driver.png'
        ];

        // Valores padronizados da Query
        $pontos_final = (float) $row['pontos_final'];
        $desclassificado = (bool) $row['desclassificado'];
        $catGroup = $row['cat_group'];

        if ($catGroup === 'Master') {
            // Init Pilot Info if needed
            if (!isset($classificacao[$eid]['pilotos_master'][$pilotoInfo['id']])) {
                $pilotoInfo['vitorias'] = 0;
                $classificacao[$eid]['pilotos_master'][$pilotoInfo['id']] = $pilotoInfo;
            }
            // Increment Wins
            if ($row['posicao'] == 1 && !$desclassificado) {
                $classificacao[$eid]['pilotos_master'][$pilotoInfo['id']]['vitorias']++;
            }

        } elseif ($catGroup === 'Challengers') {
            // Init Pilot Info if needed
            if (!isset($classificacao[$eid]['pilotos_challengers'][$pilotoInfo['id']])) {
                $pilotoInfo['vitorias'] = 0;
                $classificacao[$eid]['pilotos_challengers'][$pilotoInfo['id']] = $pilotoInfo;
            }
            // Increment Wins
            if ($row['posicao'] == 1 && !$desclassificado) {
                $classificacao[$eid]['pilotos_challengers'][$pilotoInfo['id']]['vitorias']++;
            }
        }

        // Soma apenas se for um dos Top 2 resultados da equipe na categoria nesta etapa
        if ($row['cat_rank'] <= 2) {
            if ($catGroup === 'Master') {
                $classificacao[$eid]['total_master'] += $pontos_final;
            } elseif ($catGroup === 'Challengers') {
                $classificacao[$eid]['total_challengers'] += $pontos_final;
            }
            $classificacao[$eid]['total'] += $pontos_final;
        }
    }

    // 3.5 Ajuste Final e Filtro
    // Se foi pedido um filtro específico, retornamos o total daquele filtro e ordenamos por ele
    if ($categoryFilter) {
        foreach ($classificacao as &$team) {
            if (strcasecmp($categoryFilter, 'Master') === 0) {
                $team['total'] = $team['total_master'];
            } elseif (stripos($categoryFilter, 'Challenger') !== false) {
                $team['total'] = $team['total_challengers'];
            }
        }
    }

    // 4. Ordenar
    usort($classificacao, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // 5. Adicionar Posição e converter arrays de pilotos para indexados
    $classificacao = array_values($classificacao);
    foreach ($classificacao as $k => &$item) {
        $item['pos'] = $k + 1;
        // Converter arrays associativos (keyed by pilot ID) para arrays indexados
        // para que json_encode gere arrays JS e não objetos
        $item['pilotos_master'] = array_values($item['pilotos_master']);
        $item['pilotos_challengers'] = array_values($item['pilotos_challengers']);
    }

    return $classificacao;
}
