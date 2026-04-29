<?php
// Script de diagnóstico - remover após uso
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/config/config.php';

echo "<pre style='font-family:monospace;font-size:13px;background:#111;color:#0f0;padding:20px;'>";

// 1. Checa a API Key
$apiKey = defined('GOOGLE_DRIVE_API_KEY') ? GOOGLE_DRIVE_API_KEY : 'NÃO DEFINIDA';
echo "1. API Key: " . substr($apiKey, 0, 10) . "...\n\n";

// 2. Lê fotos.txt
$fotosFile = __DIR__ . '/fotos.txt';
echo "2. fotos.txt existe: " . (file_exists($fotosFile) ? 'SIM' : 'NÃO') . "\n";
$linhas = file($fotosFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$folders = [];
foreach ($linhas as $l) {
    $l = trim($l);
    if ($l && $l[0] !== '#') {
        echo "   Linha: $l\n";
        if (preg_match('/drive\.google\.com\/drive\/folders\/([a-zA-Z0-9_-]+)/', $l, $m)) {
            $folders[] = $m[1];
        }
    }
}
echo "\n3. Pastas encontradas: " . count($folders) . "\n\n";

// 3. Testa cada pasta via API
foreach ($folders as $folderId) {
    echo "4. Testando pasta: $folderId\n";
    $apiUrl = 'https://www.googleapis.com/drive/v3/files'
        . '?q=' . urlencode("'{$folderId}' in parents and mimeType contains 'image/' and trashed=false")
        . '&fields=files(id,name)'
        . '&pageSize=10'
        . '&key=' . urlencode($apiKey);
    echo "   URL: $apiUrl\n";

    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $json = @file_get_contents($apiUrl, false, $ctx);

    if ($json === false) {
        echo "   ERRO: file_get_contents falhou (allow_url_fopen desabilitado?)\n";
        echo "   allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";
    } else {
        echo "   Resposta: " . substr($json, 0, 300) . "\n";
        $data = json_decode($json, true);
        $files = $data['files'] ?? [];
        echo "   Arquivos encontrados: " . count($files) . "\n";
        foreach (array_slice($files, 0, 3) as $f) {
            echo "     - " . $f['name'] . " (id: " . $f['id'] . ")\n";
        }
    }
    echo "\n";
}

echo "</pre>";
