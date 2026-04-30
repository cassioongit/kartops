<?php
/**
 * API CATEGORIAS - KartOps
 * Fornece dados das categorias do campeonato
 * 
 * Parâmetros:
 * - ?detailed=true : Retorna categorias detalhadas (Master, Challengers, Challenger II, Challengers III)
 *                    Usado para lançamento de resultados
 * - Sem parâmetro  : Retorna categorias agrupadas (Master, Challengers)
 *                    Usado para classificação geral
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
// Verificar se quer categorias detalhadas ou agrupadas
$detailed = isset($_GET['detailed']) && $_GET['detailed'] === 'true';

try {
    $pdo = getDBConnection();

    if ($detailed) {
        // =============================
        // MODO DETALHADO (para Resultados)
        // =============================
        // Retorna categorias como estão na tabela de pontuação
        $stmt = $pdo->query("SELECT DISTINCT categoria as nome FROM tabela_pontuacao ORDER BY 
            CASE 
                WHEN categoria = 'Master' THEN 1
                WHEN categoria = 'Challengers' THEN 2
                WHEN categoria = 'Challengers II' THEN 3
                WHEN categoria = 'Challengers III' THEN 4
                ELSE 5
            END
        ");
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Adicionar IDs fictícios e logos
        foreach ($categorias as $k => &$cat) {
            $cat['id'] = $k + 1;
            $cat['logo'] = 'images/logo-campeonato.png';
        }

        echo json_encode(['success' => true, 'data' => $categorias, 'mode' => 'detailed']);

    } else {
        // =============================
        // MODO AGRUPADO (para Classificação)
        // =============================
        // Retorna apenas Master e Challengers (agrupa todas variações)

        // 1. Buscar metadados de categorias (logos)
        $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
        $dbCats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Buscar categorias de pontuação (regras ativas)
        $stmtPontos = $pdo->query("SELECT DISTINCT categoria FROM tabela_pontuacao ORDER BY categoria ASC");
        $scoringCats = $stmtPontos->fetchAll(PDO::FETCH_COLUMN);

        // 3. Normalizar categorias: Agrupar variações de Challengers
        $normalizedCats = [];
        $seen = [];

        // Função para normalizar nome de categoria
        function normalizeCategoryName($name)
        {
            $upper = strtoupper(trim($name));
            // Todas as variações de Challenger viram "Challengers"
            if (strpos($upper, 'CHALLENGER') !== false || strpos($upper, 'CHALLENGE') !== false) {
                return 'Challengers';
            }
            // Master permanece Master
            if (strpos($upper, 'MASTER') !== false) {
                return 'Master';
            }
            // Outras categorias mantêm o nome original
            return $name;
        }

        // Processar categorias do banco
        foreach ($dbCats as $cat) {
            $normalized = normalizeCategoryName($cat['nome']);
            if (!isset($seen[$normalized])) {
                $normalizedCats[$normalized] = [
                    'id' => $cat['id'],
                    'nome' => $normalized,
                    'logo' => $cat['logo']
                ];
                $seen[$normalized] = true;
            }
        }

        // Processar categorias da pontuação
        foreach ($scoringCats as $scName) {
            $normalized = normalizeCategoryName($scName);
            if (!isset($seen[$normalized])) {
                // Buscar logo apropriada
                $logo = 'images/logo-campeonato.png';
                foreach ($dbCats as $dbC) {
                    if (normalizeCategoryName($dbC['nome']) === $normalized) {
                        $logo = $dbC['logo'];
                        break;
                    }
                }

                $normalizedCats[$normalized] = [
                    'id' => null,
                    'nome' => $normalized,
                    'logo' => $logo
                ];
                $seen[$normalized] = true;
            }
        }

        // Converter para array indexado e ordenar
        $finalCats = array_values($normalizedCats);
        usort($finalCats, function ($a, $b) {
            // Master primeiro, depois Challengers
            if ($a['nome'] === 'Master')
                return -1;
            if ($b['nome'] === 'Master')
                return 1;
            if ($a['nome'] === 'Challengers')
                return -1;
            if ($b['nome'] === 'Challengers')
                return 1;
            return strcmp($a['nome'], $b['nome']);
        });

        $categorias = $finalCats;

        echo json_encode(['success' => true, 'data' => $categorias, 'mode' => 'grouped']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao buscar categorias']);
}
?>