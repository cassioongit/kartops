<?php
/**
 * API CLASSIFICAÇÃO - KartOps
 * Retorna dados para a tabela de classificação (Pilotos e Equipes)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/classificacao_helper.php';

$tipo     = $_GET['tipo']     ?? 'pilotos';
$categoria = $_GET['categoria'] ?? '';

try {
    $pdo = getDBConnection();
    $pesoMaster = 1.3;
    $pesoChallenger = 1.0;

    // Etapas (necessário para a resposta)
    $stmtEtapas = $pdo->query("
        SELECT id, nome, data
        FROM etapas
        WHERE data <= DATE_ADD(CURDATE(), INTERVAL 8 DAY)
        ORDER BY data ASC
    ");
    $etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

    $classificacao = [];

    if ($tipo === 'pilotos') {
        // Toda a lógica de pilotos vive no helper — fonte única de verdade
        $classificacao = getClassificationData($pdo, $categoria);

    } elseif ($tipo === 'equipes') {
        // Fonte única de verdade: helper com substituição dinâmica por etapa
        $teams = getTeamClassificationData($pdo);

        foreach ($teams as &$team) {
            $mEtapas = $team['pontos_por_etapa_master']      ?? [];
            $cEtapas = $team['pontos_por_etapa_challengers'] ?? [];
            $etids   = array_keys($mEtapas + $cEtapas);

            $pontosPorEtapa = [];
            foreach ($etids as $etid) {
                $m = $mEtapas[$etid] ?? 0.0;
                $c = $cEtapas[$etid] ?? 0.0;
                if (strcasecmp($categoria, 'Master') === 0) {
                    $pontosPorEtapa[$etid] = $m;
                } elseif (stripos($categoria, 'Challenger') !== false) {
                    $pontosPorEtapa[$etid] = $c;
                } elseif (strcasecmp($categoria, 'Geral') === 0) {
                    $pontosPorEtapa[$etid] = ($m * $pesoMaster) + ($c * $pesoChallenger);
                } else {
                    $pontosPorEtapa[$etid] = $m + $c;
                }
            }

            $totalGeral = ($team['total_master'] * $pesoMaster) + ($team['total_challengers'] * $pesoChallenger);

            if (strcasecmp($categoria, 'Master') === 0) {
                $totalDisplay = $team['total_master'];
            } elseif (stripos($categoria, 'Challenger') !== false) {
                $totalDisplay = $team['total_challengers'];
            } elseif (strcasecmp($categoria, 'Geral') === 0) {
                $totalDisplay = $totalGeral;
            } else {
                $totalDisplay = $team['total'];
            }

            $team['pontos_por_etapa'] = $pontosPorEtapa;
            $team['total']            = $totalDisplay;
            $team['total_geral']      = $totalGeral;
            $team['descartes_etapas'] = [];
            unset($team['pontos_por_etapa_master'], $team['pontos_por_etapa_challengers']);
        }
        unset($team);

        usort($teams, function ($a, $b) use ($categoria) {
            if (strcasecmp($categoria, 'Master') === 0)
                return ($b['total_master'] ?? 0) <=> ($a['total_master'] ?? 0);
            if (stripos($categoria, 'Challenger') !== false)
                return ($b['total_challengers'] ?? 0) <=> ($a['total_challengers'] ?? 0);
            if (strcasecmp($categoria, 'Geral') === 0)
                return ($b['total_geral'] ?? 0) <=> ($a['total_geral'] ?? 0);
            return $b['total'] <=> $a['total'];
        });

        $classificacao = array_values($teams);
        foreach ($classificacao as $k => &$item) {
            $item['pos']             = $k + 1;
            $item['tiebreaker_used'] = null;
        }
        unset($item);
    }

    echo json_encode([
        'success' => true,
        'etapas'  => $etapas,
        'data'    => $classificacao,
        'tipo'    => $tipo
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("[KartOps] Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
