<?php
/**
 * =====================================================
 * API DE ETAPAS - KartOps
 * =====================================================
 * Endpoints para gerenciar etapas do campeonato
 * Métodos: CREATE, UPDATE, DELETE, GET
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
}
require_once __DIR__ . '/../includes/csrf.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Buscar usuário atual
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, nome, role FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
} catch (PDOException $e) {
    error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    exit;
}

// Verificar permissão (apenas Admin e Owner podem modificar)
// Visitantes (is_guest) não podem modificar, mesmo estando logados
$isGuest = isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true;
$canEdit = !$isGuest && ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner');

// Processar requisição
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? $_GET['action'] ?? '';

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    exit;
}

switch ($action) {
    case 'create':
    case 'update':
    case 'delete':
        // Validar CSRF para ações de escrita
        if (!validateCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }
        break;
}

switch ($action) {
    case 'create':
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para criar etapas']);
            exit;
        }
        createEtapa($pdo, $input, $usuario);
        break;

    case 'update':
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para editar etapas']);
            exit;
        }
        updateEtapa($pdo, $input, $usuario);
        break;

    case 'delete':
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para excluir etapas']);
            exit;
        }
        deleteEtapa($pdo, $input, $usuario);
        break;

    case 'get':
        getEtapa($pdo, $input);
        break;

    case 'list':
        listEtapas($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

/**
 * Criar nova etapa
 */
function createEtapa($pdo, $input, $usuario)
{
    // Validar campos obrigatórios
    $required = ['nome', 'data', 'hora', 'kartodromo', 'tipo_etapa'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo obrigatório: $field"]);
            exit;
        }
    }

    try {
        // Gerar UUID
        $id = sprintf(
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
            INSERT INTO etapas (id, nome, data, kartodromo, hora, patrocinador, tipo_etapa, local_variavel, data_variavel)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $id,
            trim($input['nome']),
            $input['data'],
            trim($input['kartodromo']),
            $input['hora'],
            trim($input['patrocinador'] ?? ''),
            $input['tipo_etapa'],
            $input['local_variavel'] ?? 0,
            $input['data_variavel'] ?? 0
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Etapa criada com sucesso',
            'etapaId' => $id
        ]);

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
}

/**
 * Atualizar etapa existente
 */
function updateEtapa($pdo, $input, $usuario)
{
    if (empty($input['etapaId'])) {
        echo json_encode(['success' => false, 'message' => 'ID da etapa não fornecido']);
        exit;
    }

    // Validar campos obrigatórios
    $required = ['nome', 'data', 'hora', 'kartodromo', 'tipo_etapa'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo obrigatório: $field"]);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE etapas SET 
                nome = ?, 
                data = ?, 
                kartodromo = ?, 
                hora = ?, 
                patrocinador = ?, 
                tipo_etapa = ?, 
                local_variavel = ?, 
                data_variavel = ?
            WHERE id = ?
        ");

        $stmt->execute([
            trim($input['nome']),
            $input['data'],
            trim($input['kartodromo']),
            $input['hora'],
            trim($input['patrocinador'] ?? ''),
            $input['tipo_etapa'],
            $input['local_variavel'] ?? 0,
            $input['data_variavel'] ?? 0,
            $input['etapaId']
        ]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Etapa não encontrada']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Etapa atualizada com sucesso']);

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
}

/**
 * Excluir etapa
 */
function deleteEtapa($pdo, $input, $usuario)
{
    if (empty($input['etapaId'])) {
        echo json_encode(['success' => false, 'message' => 'ID da etapa não fornecido']);
        exit;
    }

    try {
        // Buscar dados da etapa ANTES de excluir (para o email de notificação)
        $stmtGet = $pdo->prepare("SELECT nome, data, hora, kartodromo, tipo_etapa FROM etapas WHERE id = ?");
        $stmtGet->execute([$input['etapaId']]);
        $etapaData = $stmtGet->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM etapas WHERE id = ?");
        $stmt->execute([$input['etapaId']]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Etapa não encontrada']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Etapa excluída com sucesso']);

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
}

/**
 * Obter uma etapa específica
 */
function getEtapa($pdo, $input)
{
    if (empty($input['etapaId'])) {
        echo json_encode(['success' => false, 'message' => 'ID da etapa não fornecido']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM etapas WHERE id = ?");
        $stmt->execute([$input['etapaId']]);
        $etapa = $stmt->fetch();

        if (!$etapa) {
            echo json_encode(['success' => false, 'message' => 'Etapa não encontrada']);
            exit;
        }

        echo json_encode(['success' => true, 'etapa' => $etapa]);

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
}

/**
 * Listar todas as etapas
 */
function listEtapas($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM etapas ORDER BY data ASC");
        $etapas = $stmt->fetchAll();

        echo json_encode(['success' => true, 'etapas' => $etapas]);

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
}
?>