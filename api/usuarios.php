<?php
/**
 * =====================================================
 * API DE GESTÃO DE USUÁRIOS - KartOps
 * =====================================================
 * Endpoints para CRUD de usuários
 */

// Iniciar buffer de saída para capturar warnings do PHP
ob_start();

// Carregar configurações ANTES de iniciar a sessão
@require_once __DIR__ . '/../config/config.php';

// Iniciar sessão DEPOIS de carregar as configurações
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mail_helper.php';
require_once __DIR__ . '/../includes/image_helper.php';

// Limpar qualquer output (warnings) antes de enviar JSON
ob_clean();

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Buscar dados do usuário logado
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuarioLogado = $stmt->fetch();

    if (!$usuarioLogado || ($usuarioLogado['role'] !== 'Admin' && $usuarioLogado['role'] !== 'Owner')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas Owner e Admin podem acessar esta função.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor']);
    exit;
}

// Processar requisição
$input = [];
if (!empty($_POST)) {
    // Se vier via FormData/POST
    $input = $_POST;
} else {
    // Se vier via JSON Raw Body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$action = $input['action'] ?? '';

// Validar CSRF para ações de escrita
if (in_array($action, ['create', 'update', 'delete', 'toggle_status'])) {
    if (!validateCsrfToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
}




try {
    switch ($action) {
        case 'create':
            // Admin e Colaborador podem criar usuários
            if ($usuarioLogado['role'] !== 'Admin' && $usuarioLogado['role'] !== 'Colaborador') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                exit;
            }

            $nome = trim($input['nome'] ?? '');
            $email = trim($input['email'] ?? '');
            $telefone = trim($input['telefone'] ?? '');
            $contato_emergencia_nome = trim($input['contato_emergencia_nome'] ?? '');
            $contato_emergencia_telefone = trim($input['contato_emergencia_telefone'] ?? '');
            $role = $input['role'] ?? 'Usuário';
            $senha = $input['senha'] ?? '';
            $confirmarSenha = $input['confirmar_senha'] ?? '';
            $idPiloto = isset($input['id_piloto']) && $input['id_piloto'] !== '' ? $input['id_piloto'] : null;

            // Validações
            if (empty($nome) || empty($email) || empty($senha)) {
                echo json_encode(['success' => false, 'message' => 'Nome, email e senha são obrigatórios']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                exit;
            }

            // Validação de senha
            if ($senha !== $confirmarSenha) {
                echo json_encode(['success' => false, 'message' => 'As senhas não coincidem']);
                exit;
            }

            if (strlen($senha) < 8) {
                echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 8 caracteres']);
                exit;
            }

            // Validar senha forte
            if (!preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
                echo json_encode(['success' => false, 'message' => 'A senha deve conter letras maiúsculas, minúsculas e números']);
                exit;
            }

            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
                exit;
            }

            $avatarFinal = null; 


            // Gerar UUID
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );

            // Criar usuário
            $senhaHash = password_hash($senha, HASH_ALGO, ['cost' => HASH_COST]);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (id, nome, email, telefone, contato_emergencia_nome, contato_emergencia_telefone, senha_hash, avatar_url, role, id_piloto, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$uuid, $nome, $email, $telefone, $contato_emergencia_nome, $contato_emergencia_telefone, $senhaHash, $avatarFinal, $role, $idPiloto]);

            echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso']);
            break;

        case 'update':
            // Apenas Owner e Admin podem editar usuários nesta tela
            if ($usuarioLogado['role'] !== 'Admin' && $usuarioLogado['role'] !== 'Owner') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                exit;
            }

            $userId = $input['userId'] ?? 0;
            $nome = trim($input['nome'] ?? '');
            $email = trim($input['email'] ?? '');
            $telefone = trim($input['telefone'] ?? '');
            $contato_emergencia_nome = trim($input['contato_emergencia_nome'] ?? '');
            $contato_emergencia_telefone = trim($input['contato_emergencia_telefone'] ?? '');
            $role = $input['role'] ?? 'Usuário';

            // Campos extras para Owner/Admin
            $idPiloto = isset($input['id_piloto']) && $input['id_piloto'] !== '' ? $input['id_piloto'] : null;
            $ativo = isset($input['ativo']) ? (int) $input['ativo'] : 1;

            if (empty($nome) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Nome e email são obrigatórios']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                exit;
            }

            // Verificar se email existe em outro user
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
                exit;
            }

            // Recuperar avatar atual (do hidden field ou do banco se necessário, mas o hidden ajuda)
            $currentAvatar = $input['current_avatar_url'] ?? 'images/turtle-avatar.png';
            if (empty($currentAvatar))
                $currentAvatar = 'images/turtle-avatar.png';

            // Edição de avatar removida
            // $avatarFinal = processAvatarUpload($oldUser['avatar_url']);
            // Para manter o avatar existente, precisamos buscá-lo do banco de dados
            $stmtOldAvatar = $pdo->prepare("SELECT avatar_url FROM usuarios WHERE id = ?");
            $stmtOldAvatar->execute([$userId]);
            $oldUserAvatar = $stmtOldAvatar->fetchColumn();
            $avatarFinal = $oldUserAvatar; // Mantém o avatar existente, pois o upload foi removido.


            // Buscar estado atual para verificação de vínculo (apenas se for admin/owner editando)
            $oldIdPiloto = null;
            try {
                $stmtCurrent = $pdo->prepare("SELECT id_piloto FROM usuarios WHERE id = ?");
                $stmtCurrent->execute([$userId]);
                $currentUserData = $stmtCurrent->fetch();
                $oldIdPiloto = $currentUserData['id_piloto'] ?? null;
            } catch (Exception $e) {
                // Silenciar erro na busca, não deve bloquear update
            }

            // Atualizar
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET nome = ?, email = ?, telefone = ?, contato_emergencia_nome = ?, contato_emergencia_telefone = ?, avatar_url = ?, role = ?, id_piloto = ?, ativo = ?, atualizado_em = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$nome, $email, $telefone, $contato_emergencia_nome, $contato_emergencia_telefone, $avatarFinal, $role, $idPiloto, $ativo, $userId]);

            // Enviar email se houve vínculo novo de piloto
            if (!empty($idPiloto) && $idPiloto != $oldIdPiloto) {
                sendPilotLinkedEmail($email, $nome);
            }

            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
            break;

        case 'delete':
            // Owner e Admin podem excluir usuários
            if ($usuarioLogado['role'] !== 'Admin' && $usuarioLogado['role'] !== 'Owner') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Apenas administradores e owners podem excluir usuários']);
                exit;
            }

            $userId = $input['userId'] ?? '';

            if (empty($userId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
                exit;
            }

            if ($userId == $usuarioLogado['id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Você não pode excluir sua própria conta']);
                exit;
            }

            // Excluir
            try {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$userId]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                error_log("[KartOps] DB Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erro interno ao excluir usuário']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("[KartOps] DB Error: " . $e->getMessage());
    $response = json_encode(['success' => false, 'message' => 'Erro interno ao processar requisição']);
    ob_clean();
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    error_log("[KartOps] Server Error: " . $e->getMessage());
    $response = json_encode(['success' => false, 'message' => 'Erro interno inesperado']);
    ob_clean();
    echo $response;
}

// Finalizar buffer de saída apenas se ainda estiver ativo
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>