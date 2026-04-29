<?php
/**
 * Configurações de E-mail (Gmail SMTP)
 */

// Proteção contra acesso direto
require_once __DIR__ . '/../includes/security.php';
blockDirectAccess(__FILE__);

// Se houver necessidade de carregar o env separadamente (caso não use config.php)
if (!function_exists('envVar')) {
    require_once __DIR__ . '/../includes/env.php';
}

// Seus dados do Gmail (com fallback para getenv/containers)
define('SMTP_HOST', envVar('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', envVar('SMTP_PORT', 587));
define('SMTP_USER', envVar('SMTP_USER', ''));
define('SMTP_PASS', envVar('SMTP_PASS', ''));

// Configurações de Remetente
define('MAIL_FROM', envVar('MAIL_FROM', 'noreply@kartops.com'));
define('MAIL_NAME', envVar('MAIL_NAME', 'KartOps'));

// Debug
define('MAIL_DEBUG', envVar('MAIL_DEBUG', 'false') === 'true');
?>