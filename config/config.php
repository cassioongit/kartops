<?php
/**
 * =====================================================
 * CONFIGURAÇÃO DO BANCO DE DADOS - KARTOPS
 * =====================================================
 */

// Proteção contra acesso direto
require_once __DIR__ . '/../includes/security.php';
blockDirectAccess(__FILE__);

// Incluir o carregador de variáveis de ambiente
require_once __DIR__ . '/../includes/env.php';

// Configurações do Banco de Dados (com fallback para getenv/containers)
define('DB_HOST', envVar('DB_HOST', 'localhost'));
define('DB_NAME', envVar('DB_NAME', 'datakart'));
define('DB_USER', envVar('DB_USER', 'root'));
define('DB_PASS', envVar('DB_PASS', ''));
define('DB_PORT', envVar('DB_PORT', '3306'));
define('DB_CHARSET', envVar('DB_CHARSET', 'utf8mb4'));

// Configurações da Aplicação
define('APP_NAME', envVar('APP_NAME', 'KartOps'));
define('APP_URL', envVar('APP_URL', 'http://localhost:8000'));
define('APP_VERSION', envVar('APP_VERSION', '1.6'));
define('TIMEZONE', envVar('TIMEZONE', 'America/Sao_Paulo'));

// Google Drive API Key (ex: AIzaSy...) - necessária para ler fotos de pastas
define('GOOGLE_DRIVE_API_KEY', envVar('GOOGLE_DRIVE_API_KEY', ''));

// Google OAuth (Login Social)
define('GOOGLE_CLIENT_ID', envVar('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', envVar('GOOGLE_CLIENT_SECRET', ''));

// Determinar a URL de redirecionamento dinamicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$currentBaseUrl = "$protocol://$host";

// Fallback: Se APP_URL estiver definido e não for o padrão localhost, usamos ele
if (defined('APP_URL') && strpos(APP_URL, 'localhost') === false) {
    $currentBaseUrl = APP_URL;
}

define('GOOGLE_REDIRECT_URI', rtrim($currentBaseUrl, '/') . '/google_callback.php');

// HEADERS DE CACHE CONTROL - Desabilitar cache de navegador para páginas PHP
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// HEADERS DE SEGURANÇA
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
// CSP básica
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com cdnjs.cloudflare.com; font-src fonts.gstatic.com cdnjs.cloudflare.com; img-src 'self' data: lh3.googleusercontent.com blob:; connect-src 'self' https://accounts.google.com; frame-src 'self' https://accounts.google.com https://www.youtube.com;");

// Configurações de Sessão
define('SESSION_NAME', 'kartops_session');
define('SESSION_LIFETIME', 3600 * 24 * 15); // 15 dias

// Definir caminho de sessão fora da raiz web (segurança)
$sessionPath = sys_get_temp_dir() . '/kartops_sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
session_save_path($sessionPath);

// Configurar parâmetros do cookie
ini_set('session.name', SESSION_NAME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
ini_set('session.cookie_secure', $isSecure ? 1 : 0);

define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

date_default_timezone_set(TIMEZONE);

function getDBConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Garantir que a conexão ao banco usa o fuso horário correto
        $pdo->exec("SET time_zone = '-03:00'");

        return $pdo;
    } catch (PDOException $e) {
        error_log("[KartOps] Erro Fatal: " . $e->getMessage());
    die('Erro interno. Tente novamente.');
    }
}

// Iniciar sessão de forma unificada para toda a aplicação após configurações de cookie e ini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
