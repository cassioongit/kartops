<?php
/**
 * =====================================================
 * ENV LOADER - KartOps
 * =====================================================
 * Carrega variáveis de ambiente do .env com validações de segurança.
 * Suporta fallback para getenv() (containers/cloud).
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);

/**
 * Carrega variáveis de um arquivo .env
 */
function loadEnv($path)
{
    if (!file_exists($path)) {
        return false;
    }

    // Verificar permissões do arquivo .env
    $perms = fileperms($path) & 0777;
    if ($perms & 0004) { // world-readable
        error_log("[KartOps SEGURANÇA] ATENÇÃO: Arquivo .env ({$path}) com permissão world-readable ({$perms}). Recomendado: chmod 600 .env");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue; // Ignorar linhas mal formatadas
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        // Remover aspas ao redor do valor (simples ou duplas)
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

/**
 * Obtém variável de ambiente com fallback para getenv().
 * Permite deploy em containers/cloud onde as vars vêm do sistema.
 * 
 * @param string $key Nome da variável
 * @param mixed $default Valor padrão se não encontrada
 * @return mixed
 */
function envVar(string $key, $default = null)
{
    // 1. Verificar $_ENV (carregado pelo loadEnv ou pelo PHP)
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    // 2. Verificar $_SERVER (algumas configurações de PHP)
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    // 3. Fallback para getenv() (containers, Docker, cloud)
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    return $default;
}

// Carregar o arquivo .env da raiz do projeto
loadEnv(__DIR__ . '/../.env');
