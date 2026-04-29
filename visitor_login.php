<?php
/**
 * =====================================================
 * LOGIN COMO VISITANTE - KartOps
 * =====================================================
 * Cria uma sessão temporária como visitante sem cadastro
 */

require_once 'config/config.php';

// Configurar persistência (15 dias)
$lifetime = 3600 * 24 * 15;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    // Domain removido - localhost
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name('kartops_session');
session_start();

// Criar sessão de visitante
$_SESSION['user_id'] = 'visitor_' . uniqid(); // ID temporário único
$_SESSION['user_email'] = 'visitante@KartOps.com';
$_SESSION['user_name'] = 'Lentidão Jr.';
$_SESSION['user_role'] = 'visitante';
$_SESSION['user_avatar'] = 'images/turtle-avatar.png'; // Avatar padrão
$_SESSION['is_guest'] = true; // Flag para identificar visitante

// Redirecionar para home
header('Location: home.php');
exit;
?>