<?php
/**
 * API de Notificações — envia emails individuais
 * Chamada via AJAX pela página de progresso
 */
if (!defined('KARTOPS_APP'))
    define('KARTOPS_APP', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mail_helper.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
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
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados']);
    exit;
}

// Verificar permissão (apenas Admin e Owner podem enviar notificações)
if (!in_array($usuario['role'], ['Admin', 'Owner'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'list_users':
        // Retornar lista de todos os usuários com email
        try {
            $stmt = $pdo->query("SELECT id, nome, email FROM usuarios WHERE email IS NOT NULL AND email != '' ORDER BY nome ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users, 'total' => count($users)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar usuários']);
        }
        break;

    case 'send_single':
        // Validar CSRF
        validateCsrfToken();

        $email = $input['email'] ?? '';
        $nome = $input['nome'] ?? '';
        $tipoAcao = $input['tipo_acao'] ?? 'update';
        $etapaData = $input['etapa_data'] ?? [];
        $adminNome = $usuario['nome'] ?? 'Administrador';

        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email não fornecido']);
            exit;
        }

        $result = sendEtapaNotificationToUser($tipoAcao, $etapaData, $email, $nome, $adminNome);

        echo json_encode([
            'success' => $result,
            'email' => $email,
            'nome' => $nome,
            'message' => $result ? 'Enviado com sucesso' : 'Falha no envio'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
?>