<?php
/**
 * =====================================================
 * LOGOUT - KartOps
 * =====================================================
 */

// Incluir conexão para limpar token do banco (opcional, mas bom para Single Session)
require_once 'config/config.php';
// Limpar token no banco se estiver logado
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE usuarios SET session_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Ignora erro de DB no logout
    }
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Apagar o cookie da sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: index.php?msg=Você saiu do sistema');
exit;
?>