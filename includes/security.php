<?php
/**
 * =====================================================
 * SECURITY GUARDS - KartOps
 * =====================================================
 * Proteções PHP que funcionam independente do servidor web.
 * Esta camada complementa o .htaccess (Apache) e funciona
 * também em Nginx, LiteSpeed, ou qualquer outro servidor.
 */

if (!defined('KARTOPS_APP')) {
    define('KARTOPS_APP', true);
}

/**
 * Bloqueia a execução se o arquivo estiver sendo acessado diretamente.
 * Deve ser chamado no topo de arquivos de configuração e includes.
 * 
 * Uso: blockDirectAccess(__FILE__);
 * 
 * Compara o caminho do arquivo protegido com o SCRIPT_FILENAME
 * (que é o arquivo que o servidor web está executando).
 * Se forem iguais, significa acesso direto via navegador.
 */
function blockDirectAccess(string $protectedFile): void
{
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if ($scriptFile && realpath($protectedFile) === realpath($scriptFile)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('Acesso direto não permitido.');
    }
}
