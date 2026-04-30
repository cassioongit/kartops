<?php
// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);

// IMPORTANTE: Carregar config.php ANTES de qualquer verificação de sessão
// O config.php define o session_save_path correto para a aplicação
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../config/config.php';
}

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Definir nome da sessão antes de iniciar
    if (!defined('SESSION_NAME'))
        define('SESSION_NAME', 'kartops_session');

    // Configurar persistência (30 dias)
    $lifetime = 3600 * 24 * 30; // 30 dias

    ini_set('session.gc_maxlifetime', $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Buscar dados do usuário se estiver logado
$usuario = null;
if (isset($_SESSION['user_id'])) {
    // Se for visitante temporário (sem cadastro no BD)
    if (isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true) {
        $usuario = [
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'avatar_url' => $_SESSION['user_avatar'] ?? 'images/turtle-avatar.png',
            'role' => $_SESSION['user_role'] === 'visitante' ? 'Usuário' : $_SESSION['user_role'],
            'ativo' => 1,
            'id_piloto' => null,
            'criado_em' => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s')
        ];
        if ($usuario['role'] === 'visitante')
            $usuario['role'] = 'Usuário';

    } else {
        // Usuário cadastrado no banco
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, nome, email, avatar_url, role, ativo, id_piloto, session_token, criado_em, atualizado_em FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            session_destroy();
            header('Location: index.php?msg=Conta não encontrada');
            exit;
        }

        if (!$usuario['ativo']) {
            session_destroy();
            header('Location: index.php?msg=Conta desativada');
            exit;
        }

        // Validar session_token para garantir que esta sessão é válida
        if (isset($_SESSION['session_token']) && !empty($usuario['session_token'])) {
            if ($_SESSION['session_token'] !== $usuario['session_token']) {
                session_destroy();
                header('Location: index.php?msg=Sessão expirada. Faça login novamente.');
                exit;
            }
        }
    }
}

// =====================================================
// CSRF Token Management (carregado de csrf.php)
// =====================================================
require_once __DIR__ . '/csrf.php';
