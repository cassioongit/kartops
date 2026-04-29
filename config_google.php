<?php
/**
 * =====================================================
 * GOOGLE OAUTH CONFIG - KartOps
 * =====================================================
 * Os valores reais são carregados pelo .env via config.php.
 * Este arquivo apenas define constantes a partir das vars de ambiente.
 * Não contém segredos — segredos ficam no .env (não versionado).
 */

// Proteção contra acesso direto
require_once __DIR__ . '/includes/security.php';
blockDirectAccess(__FILE__);

// Garantir que envVar() está disponível
if (!function_exists('envVar')) {
    require_once __DIR__ . '/includes/env.php';
}

// Google OAuth (valores vêm do .env)
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', envVar('GOOGLE_CLIENT_ID', ''));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', envVar('GOOGLE_CLIENT_SECRET', ''));
}

// Determinar a URL de redirecionamento dinamicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$base_url = "$protocol://$host";

// Fallback: usar APP_URL se definido e não for localhost
if (defined('APP_URL') && strpos(APP_URL, 'localhost') === false) {
    $base_url = APP_URL;
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', rtrim($base_url, '/') . '/google_callback.php');
}
