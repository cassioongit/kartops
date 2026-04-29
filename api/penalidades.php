<?php
/**
 * API PENALIDADES - KartOps
 * Gerencia penalidades aplicadas aos pilotos
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/utils_resultados.php';

session_start();
require_once __DIR__ . '/../includes/csrf.php';

// Validar Acesso
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Buscar dados do usuário pelo BD (padrão consistente)
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
            // Escrita (create, update, delete) - validar CSRF
            if (!validateCsrfToken()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
                exit;
            }
    }

    switch ($action) {
        case 'list':
            // Listar penalidades
            $resultado_id = $_GET['resultado_id'] ?? null;
            $piloto_id = $_GET['piloto_id'] ?? null;
            $etapa_id = $_GET['etapa_id'] ?? null;

            $sql = "
                SELECT p.*, 
                       tp.codigo as tipo_codigo,
                       tp.nome as tipo_nome,
                       tp.pontos_padrao,
                       u.nome as criado_por_nome
                FROM penalidades p
                LEFT JOIN tipos_penalidade tp ON p.tipo_penalidade_id = tp.id
                LEFT JOIN usuarios u ON p.criado_por = u.id
                WHERE 1=1
            ";

            $params = [];

            if ($resultado_id) {
                $sql .= " AND p.resultado_id = ?";
                $params[] = $resultado_id;
            }

            if ($piloto_id && $etapa_id) {
                $sql .= " AND p.piloto_id = ? AND p.etapa_id = ?";
                $params[] = $piloto_id;
                $params[] = $etapa_id;
            }

            $sql .= " ORDER BY p.criado_em DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $penalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $penalidades]);
            break;

        case 'tipos':
            // Listar tipos de penalidade disponíveis
            $stmt = $pdo->query("
                SELECT * FROM tipos_penalidade 
                WHERE ativo = TRUE 
                ORDER BY nome ASC
            ");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $tipos]);
            break;

        case 'create':
            if (!$canWrite) {
                throw new Exception('Permissão negada');
            }

            $resultado_id = $input['resultado_id'] ?? null;
            $tipo_penalidade_id = $input['tipo_penalidade_id'] ?? null;
            $pontos_custom = isset($input['pontos_custom']) ? (float) $input['pontos_custom'] : null;
            $observacoes = trim($input['observacoes'] ?? '');

            if (!$resultado_id || !$tipo_penalidade_id) {
                throw new Exception('Dados incompletos');
            }

            // Buscar dados do resultado
            $stmt = $pdo->prepare("
                SELECT r.*, p.id as piloto_id, e.id as etapa_id 
                FROM resultados r
                LEFT JOIN pilotos p ON r.piloto_id = p.id
                LEFT JOIN etapas e ON r.etapa_id = e.id
                WHERE r.id = ?
            ");
            $stmt->execute([$resultado_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado) {
                throw new Exception('Resultado não encontrado');
            }

            // Buscar tipo de penalidade
            $stmt = $pdo->prepare("SELECT * FROM tipos_penalidade WHERE id = ?");
            $stmt->execute([$tipo_penalidade_id]);
            $tipoPenalidade = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tipoPenalidade) {
                throw new Exception('Tipo de penalidade não encontrado');
            }

            // Se for desclassificação, marcar resultado como desclassificado
            $desclassificado = ($tipoPenalidade['codigo'] === 'desclassificacao');

            // Usar pontos customizados ou padrão
            $pontos_penalidade = $pontos_custom !== null ? $pontos_custom : $tipoPenalidade['pontos_padrao'];
            $tempo_adicional = $tipoPenalidade['adiciona_tempo_segundos'] ?? 0;

            // Criar penalidade
            $penalidade_id = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );

            $stmt = $pdo->prepare("
                INSERT INTO penalidades 
                (id, resultado_id, piloto_id, etapa_id, tipo_penalidade_id, pontos_penalidade, tempo_adicional_segundos, observacoes, desclassificado, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $penalidade_id,
                $resultado_id,
                $resultado['piloto_id'],
                $resultado['etapa_id'],
                $tipo_penalidade_id,
                $pontos_penalidade,
                $tempo_adicional,
                $observacoes,
                $desclassificado ? 1 : 0,
                $_SESSION['user_id']
            ]);

            // Recalcular pontos do resultado
            recalcularPontosResultado($pdo, $resultado_id);

            echo json_encode(['success' => true, 'message' => 'Penalidade aplicada com sucesso']);
            break;

        case 'update':
            if (!$canWrite) {
                throw new Exception('Permissão negada');
            }

            $id = $input['id'] ?? null;
            $pontos_custom = isset($input['pontos_custom']) ? (float) $input['pontos_custom'] : null;
            $observacoes = trim($input['observacoes'] ?? '');

            if (!$id) {
                throw new Exception('ID necessário');
            }

            // Buscar penalidade
            $stmt = $pdo->prepare("
                SELECT p.*, tp.pontos_padrao, tp.codigo
                FROM penalidades p
                LEFT JOIN tipos_penalidade tp ON p.tipo_penalidade_id = tp.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $penalidade = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$penalidade) {
                throw new Exception('Penalidade não encontrada');
            }

            // Atualizar pontos (usar custom ou padrão)
            $pontos_penalidade = $pontos_custom !== null ? $pontos_custom : $penalidade['pontos_padrao'];

            $stmt = $pdo->prepare("
                UPDATE penalidades 
                SET pontos_penalidade = ?, observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$pontos_penalidade, $observacoes, $id]);

            // Recalcular pontos do resultado
            recalcularPontosResultado($pdo, $penalidade['resultado_id']);

            echo json_encode(['success' => true, 'message' => 'Penalidade atualizada com sucesso']);
            break;

        case 'delete':
            if (!$canWrite) {
                throw new Exception('Permissão negada');
            }

            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('ID necessário');
            }

            // Buscar penalidade antes de deletar
            $stmt = $pdo->prepare("SELECT resultado_id, desclassificado FROM penalidades WHERE id = ?");
            $stmt->execute([$id]);
            $penalidade = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$penalidade) {
                throw new Exception('Penalidade não encontrada');
            }

            // Deletar penalidade
            $stmt = $pdo->prepare("DELETE FROM penalidades WHERE id = ?");
            $stmt->execute([$id]);

            // Recalcular pontos do resultado (verificar se ainda há desclassificação)
            recalcularPontosResultado($pdo, $penalidade['resultado_id']);

            echo json_encode(['success' => true, 'message' => 'Penalidade removida com sucesso']);
            break;

        default:
            throw new Exception("Ação inválida: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Recalcula os pontos do resultado considerando penalidades
 */
function recalcularPontosResultado($pdo, $resultado_id)
{
    // Buscar resultado
    $stmt = $pdo->prepare("SELECT * FROM resultados WHERE id = ?");
    $stmt->execute([$resultado_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        return;
    }

    $posicaoAnterior = (int) $resultado['posicao'];

    // Verificar se há desclassificação ativa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM penalidades 
        WHERE resultado_id = ? AND desclassificado = 1
    ");
    $stmt->execute([$resultado_id]);
    $hasDesclassificacao = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;

    if ($hasDesclassificacao) {
        // Se desclassificado, zerar pontos e posição
        $stmt = $pdo->prepare("
            UPDATE resultados 
            SET pontos = 0, 
                pontos_penalidade = 0, 
                desclassificado = 1,
                posicao = 0
            WHERE id = ?
        ");
        $stmt->execute([$resultado_id]);

        // Se ele tinha uma posição válida, fechar o buraco que ele deixou
        if ($posicaoAnterior > 0) {
            fecharBuracoPosicao($pdo, $resultado['etapa_id'], $posicaoAnterior, $resultado['categoria']);
        }
    } else {
        // Calcular soma de penalidades
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(pontos_penalidade), 0) as total_penalidades
            FROM penalidades 
            WHERE resultado_id = ?
        ");
        $stmt->execute([$resultado_id]);
        $totalPenalidades = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total_penalidades'];

        // Atualizar resultado (mantendo a posição, se houver)
        $stmt = $pdo->prepare("
            UPDATE resultados 
            SET pontos_penalidade = ?,
                desclassificado = 0
            WHERE id = ?
        ");
        $stmt->execute([$totalPenalidades, $resultado_id]);
    }

    // Sempre recalcular a etapa toda para garantir pontos corretos
    recalcularPontosEtapa($pdo, $resultado['etapa_id']);
}
