<?php
/**
 * =====================================================
 * CSRF Token Helper - KartOps
 * =====================================================
 * Funções para geração e validação de tokens CSRF.
 * Requer que a sessão já esteja iniciada.
 * 
 * Incluído automaticamente por auth_session.php (páginas)
 * Incluído manualmente nas APIs: require_once __DIR__ . '/csrf.php';
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);

// Gerar token CSRF se não existir na sessão
if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Retorna o token CSRF atual da sessão
 */
function getCsrfToken(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Gera um campo hidden HTML com o token CSRF para formulários
 * Uso: <?= generateCsrfField() ?> dentro de <form>
 */
function generateCsrfField(): string
{
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Valida o token CSRF recebido via POST, JSON body ou header X-CSRF-Token
 * Retorna true se válido, false se inválido
 */
function validateCsrfToken(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    // 1. Tentar via POST field
    if (isset($_POST['csrf_token'])) {
        return hash_equals($sessionToken, $_POST['csrf_token']);
    }

    // 2. Tentar via JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['csrf_token'])) {
        return hash_equals($sessionToken, $input['csrf_token']);
    }

    // 3. Tentar via Header (para chamadas fetch/AJAX)
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($headerToken)) {
        return hash_equals($sessionToken, $headerToken);
    }

    return false;
}
