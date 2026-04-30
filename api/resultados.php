<?php
/**
 * API RESULTADOS - KartOps
 * Gerencia lançamentos de resultados das etapas
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/utils_resultados.php';
require_once __DIR__ . '/../includes/csrf.php';

// Validar Acesso
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Buscar dados do usuário pelo BD (padrão consistente com api/etapas.php)
$pdo = getDBConnection();
$stmtUser = $pdo->prepare("SELECT id, nome, role FROM usuarios WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$canWrite = in_array($usuario['role'], ['Admin', 'Owner', 'Colaborador']);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();

    switch ($action) {
        case 'list':
            // Leitura - sem CSRF
            break;
        default:
            // Escrita (save, delete) - validar CSRF
            if (!validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
    }

    switch ($action) {
        case 'list':
            // Listar resultados. Se tiver etapa_id, filtra. Senão, traz tudo (limitado ou paginado idealmente)
            $etapa_id = $_GET['etapa_id'] ?? null;
            $categoria = $_GET['categoria'] ?? null;
            $piloto_id = $_GET['piloto_id'] ?? null;

            $sql = "
                SELECT r.*, 
                       p.nome as piloto_nome, p.foto as piloto_foto, p.id as piloto_id,
                       e.nome as equipe_nome, e.cor as equipe_cor, e.imagem as equipe_imagem,
                       et.nome as etapa_nome, et.data as etapa_data, et.kartodromo as etapa_kartodromo, et.id as etapa_id,
                       COALESCE(r.pontos_penalidade, 0) as pontos_penalidade,
                       COALESCE(r.desclassificado, 0) as desclassificado,
                       CASE 
                           WHEN r.desclassificado = 1 THEN 0
                           ELSE r.pontos
                       END as pontos_final,
                       (r.pontos + ABS(COALESCE(r.pontos_penalidade, 0))) as pontos,
                       CAST(COALESCE(r.pontos_penalidade, 0) AS DECIMAL(10,2)) as pontos_penalidade_val
                FROM resultados r
                LEFT JOIN pilotos p ON r.piloto_id = p.id
                LEFT JOIN equipes e ON r.equipe_id = e.id
                LEFT JOIN etapas et ON r.etapa_id = et.id
                WHERE 1=1
            ";

            $params = [];

            if ($etapa_id) {
                $sql .= " AND r.etapa_id = ?";
                $params[] = $etapa_id;
            }

            if ($categoria) {
                $sql .= " AND r.categoria = ?";
                $params[] = $categoria;
            }

            if ($piloto_id) {
                $sql .= " AND r.piloto_id = ?";
                $params[] = $piloto_id;
            }

            // Ordenação: Tratando posição 0 (NC) como última
            $orderByPosicao = "CASE WHEN r.posicao = 0 THEN 9999 ELSE r.posicao END ASC";

            if ($etapa_id) {
                $sql .= " ORDER BY $orderByPosicao, r.pontos DESC";
            } else {
                $sql .= " ORDER BY et.data DESC, et.hora DESC, $orderByPosicao LIMIT 200";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular quem pontua para equipe (os DOIS melhores colocados de cada equipe por categoria e etapa)
            // Agrupar por equipe_id + categoria + etapa_id
            $pilotosPorEquipe = [];
            foreach ($resultados as $r) {
                if (!$r['equipe_id'] || $r['posicao'] == 0 || $r['desclassificado'] == 1) {
                    continue; // Sem equipe, falta ou desclassificado não pontua
                }

                $chave = $r['equipe_id'] . '|' . $r['categoria'] . '|' . $r['etapa_id'];

                if (!isset($pilotosPorEquipe[$chave])) {
                    $pilotosPorEquipe[$chave] = [];
                }

                // Adicionar piloto à lista da equipe/categoria/etapa
                $pilotosPorEquipe[$chave][] = [
                    'id' => $r['id'],
                    'posicao' => $r['posicao']
                ];
            }

            // Para cada grupo, ordenar por posição e pegar os 2 melhores
            $pontuaParaEquipeSet = [];
            foreach ($pilotosPorEquipe as $chave => $pilotos) {
                // Ordenar por posição (menor = melhor)
                usort($pilotos, function ($a, $b) {
                    return $a['posicao'] - $b['posicao'];
                });

                // Pegar os 2 primeiros (melhores colocados)
                $count = min(2, count($pilotos));
                for ($i = 0; $i < $count; $i++) {
                    $pontuaParaEquipeSet[$pilotos[$i]['id']] = true;
                }
            }

            // Adicionar campo em cada resultado
            foreach ($resultados as &$r) {
                $r['pontua_para_equipe'] = isset($pontuaParaEquipeSet[$r['id']]) ? 1 : 0;
            }
            unset($r); // Limpar referência

            echo json_encode(['success' => true, 'data' => $resultados]);
            break;

        case 'save':
            if (!$canWrite)
                throw new Exception('Permissão negada');

            $id = $input['id'] ?? null; // Se vier ID, é update
            $etapa_id = $input['etapa_id'];
            $piloto_id = $input['piloto_id'];
            $posicaoNova = (int) $input['posicao']; // 0 = NC
            $piloto_categoria = $input['categoria'] ?? null;
            $piloto_equipe = $input['equipe_id'] ?? null;

            $pdo->beginTransaction();
            try {
                // 1. Buscar validade da Etapa e Tipo
                $stmt = $pdo->prepare("SELECT id, tipo_etapa FROM etapas WHERE id = ?");
                $stmt->execute([$etapa_id]);
                $etapaInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$etapaInfo)
                    throw new Exception('Etapa não encontrada');

                $tipoEtapa = $etapaInfo['tipo_etapa'] ?? 'Normal';
                $isEndurance = (stripos($tipoEtapa, 'Endurance') !== false);
                $entidadeComparacao = $isEndurance ? 'equipe_id' : 'piloto_id';
                
                // Precisamos dos dados do piloto agora para saber a equipe caso seja endurance
                if (!$piloto_categoria || !$piloto_equipe) {
                    $stmtP = $pdo->prepare("SELECT p.*, c.nome as cat_nome FROM pilotos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ?");
                    $stmtP->execute([$piloto_id]);
                    $pilotoData = $stmtP->fetch(PDO::FETCH_ASSOC);
                    if (!$pilotoData) throw new Exception('Piloto não encontrado');
                    if (!$piloto_equipe) $piloto_equipe = $pilotoData['equipe_id'];
                    if (!$piloto_categoria) $piloto_categoria = $pilotoData['cat_nome'] ?? 'MASTER';
                }

                $entidadeValor = $isEndurance ? $piloto_equipe : $piloto_id;

                // 2. Verificar posição antiga e ID existente
                $posicaoAntiga = 0;
                $tempId = $id;

                if (!$tempId) {
                    $sqlCheck = "SELECT id, posicao FROM resultados WHERE etapa_id=? AND $entidadeComparacao=?";
                    $check = $pdo->prepare($sqlCheck);
                    $check->execute([$etapa_id, $entidadeValor]);
                    if ($existing = $check->fetch()) {
                        $tempId = $existing['id'];
                        $posicaoAntiga = (int)$existing['posicao'];
                    }
                } else {
                    $check = $pdo->prepare("SELECT posicao FROM resultados WHERE id=?");
                    $check->execute([$tempId]);
                    if ($existing = $check->fetch()) {
                        $posicaoAntiga = (int)$existing['posicao'];
                    }
                }

                // 4. Salvar (Insert ou Update)
                if ($tempId) {
                    $sqlUp = "UPDATE resultados SET piloto_id=?, equipe_id=?, posicao=?, categoria=?, pontos=0 WHERE id=?";
                    $stmtUp = $pdo->prepare($sqlUp);
                    $stmtUp->execute([$piloto_id, $piloto_equipe, $posicaoNova, $piloto_categoria, $tempId]);
                    $finalId = $tempId;
                } else {
                    $finalId = sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );

                    $sqlIns = "INSERT INTO resultados (id, etapa_id, piloto_id, equipe_id, posicao, categoria, pontos) VALUES (?, ?, ?, ?, ?, ?, 0)";
                    $stmtIns = $pdo->prepare($sqlIns);
                    $stmtIns->execute([$finalId, $etapa_id, $piloto_id, $piloto_equipe, $posicaoNova, $piloto_categoria]);
                }

                // 5. Algoritmo de Cascata (Tarefa 6)
                if ($posicaoNova > 0) {
                    if ($posicaoAntiga == 0) {
                        // Inserção ou Promoção vindo de NC: Empurra todos para trás (+1)
                        $sqlCascata = "UPDATE resultados SET posicao = posicao + 1 WHERE etapa_id = ? AND categoria = ? AND posicao >= ? AND id != ?";
                        $pdo->prepare($sqlCascata)->execute([$etapa_id, $piloto_categoria, $posicaoNova, $finalId]);
                    } elseif ($posicaoNova < $posicaoAntiga) {
                        // Subiu de posição: Empurra quem estava no meio para trás (+1)
                        $sqlCascata = "UPDATE resultados SET posicao = posicao + 1 WHERE etapa_id = ? AND categoria = ? AND posicao >= ? AND posicao < ? AND id != ?";
                        $pdo->prepare($sqlCascata)->execute([$etapa_id, $piloto_categoria, $posicaoNova, $posicaoAntiga, $finalId]);
                    } elseif ($posicaoNova > $posicaoAntiga) {
                        // Desceu de posição (Punição): Puxa quem estava no meio para frente (-1)
                        $sqlCascata = "UPDATE resultados SET posicao = posicao - 1 WHERE etapa_id = ? AND categoria = ? AND posicao > ? AND posicao <= ? AND id != ?";
                        $pdo->prepare($sqlCascata)->execute([$etapa_id, $piloto_categoria, $posicaoAntiga, $posicaoNova, $finalId]);
                    }
                } else if ($posicaoAntiga > 0) {
                    // Foi para NC: Fecha o buraco deixado (-1)
                    $sqlCascata = "UPDATE resultados SET posicao = posicao - 1 WHERE etapa_id = ? AND categoria = ? AND posicao > ? AND id != ?";
                    $pdo->prepare($sqlCascata)->execute([$etapa_id, $piloto_categoria, $posicaoAntiga, $finalId]);
                }

                // 6. Recalcular e Finalizar
                recalcularPontosEtapa($pdo, $etapa_id);
                
                $pdo->commit();

                // Obter pontos calculados para o retorno
                $stmtPts = $pdo->prepare("SELECT pontos FROM resultados WHERE id = ?");
                $stmtPts->execute([$finalId]);
                $resPts = $stmtPts->fetch(PDO::FETCH_ASSOC);
                $pontosCalculados = $resPts ? (float)$resPts['pontos'] : 0;

                echo json_encode(['success' => true, 'pontos_calculados' => $pontosCalculados]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'lancar_zerados':
            if (!$canWrite)
                throw new Exception('Permissão negada');

            $etapa_id = $input['etapa_id'] ?? null;
            $categoria_filtro = $input['categoria'] ?? null;
            if (!$etapa_id)
                throw new Exception('ID da Etapa é necessário');

            $pdo->beginTransaction();
            try {
                // 1. Pega os pilotos elegíveis
                // Precisamos normalizar o filtro (Ex: "Challengers III" -> "Challengers") para bater com a tabela de pilotos
                $sqlInscritos = "SELECT p.id, p.equipe_id, c.nome as base_cat_nome 
                                 FROM pilotos p 
                                 JOIN categorias c ON p.categoria_id = c.id";
                $paramsInscritos = [];
                
                if ($categoria_filtro) {
                    // Lógica de normalização igual ao do sistema de classificação
                    $normalized = strtoupper(trim($categoria_filtro));
                    if (strpos($normalized, 'CHALLENGER') !== false || strpos($normalized, 'CHALLENGE') !== false) {
                        $normalized = 'Challengers';
                    } elseif (strpos($normalized, 'MASTER') !== false) {
                        $normalized = 'Master';
                    } else {
                        $normalized = $categoria_filtro;
                    }

                    $sqlInscritos .= " WHERE c.nome = ?";
                    $paramsInscritos[] = $normalized;
                }
                
                $stmtInscritos = $pdo->prepare($sqlInscritos);
                $stmtInscritos->execute($paramsInscritos);
                $pilotos = $stmtInscritos->fetchAll(PDO::FETCH_ASSOC);

                $novosAdicionados = 0;
                
                // Preparar inserção
                $sqlIns = "INSERT INTO resultados (id, etapa_id, piloto_id, equipe_id, posicao, categoria, pontos) VALUES (?, ?, ?, ?, 0, ?, 0)";
                $stmtIns = $pdo->prepare($sqlIns);
                
                // Checagem de existência otimizada num Set
                $stmtRes = $pdo->prepare("SELECT piloto_id FROM resultados WHERE etapa_id = ?");
                $stmtRes->execute([$etapa_id]);
                $existentesDb = $stmtRes->fetchAll(PDO::FETCH_COLUMN);
                $existentesSet = array_flip($existentesDb);

                foreach ($pilotos as $p) {
                    if (!isset($existentesSet[$p['id']])) {
                        // Insert!
                        $newId = sprintf(
                            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );
                        
                        // Se houver filtro, usamos o nome do filtro (Ex: "Challengers III")
                        // Caso contrário, usamos o nome base (Ex: "Master")
                        $cat_nome_final = $categoria_filtro ?: $p['base_cat_nome'];
                        
                        $stmtIns->execute([$newId, $etapa_id, $p['id'], $p['equipe_id'], $cat_nome_final]);
                        $novosAdicionados++;
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => "$novosAdicionados pilotos receberam posição 0 em lote."]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'reset_test_stage':
            if (!$canWrite)
                throw new Exception('Permissão negada');
            
            // Comando especial solicitado: "Reset pls"
            // Deleta apenas a etapa "Etapa de Teste Lógica" (15/03/2026) e seus resultados
            $nomeTeste = 'Etapa de Teste Lógica';
            $dataTeste = '2026-03-15';

            $pdo->beginTransaction();
            try {
                // 1. Achar o ID da etapa
                $st = $pdo->prepare("SELECT id FROM etapas WHERE nome = ? AND data = ?");
                $st->execute([$nomeTeste, $dataTeste]);
                $et = $st->fetch();

                if ($et) {
                    $etid = $et['id'];
                    // 2. Deletar apenas os resultados
                    $pdo->prepare("DELETE FROM resultados WHERE etapa_id = ?")->execute([$etid]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Resultados da etapa de teste eliminados com Reset pls. A etapa foi mantida.']);
                } else {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Etapa de teste não encontrada.']);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'delete':
            if (!$canWrite)
                throw new Exception('Permissão negada');
            $id = $input['id'] ?? null;
            if (!$id)
                throw new Exception('ID necessário');

            $pdo->prepare("DELETE FROM resultados WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Ação inválida: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>