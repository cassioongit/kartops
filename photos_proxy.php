<?php
/**
 * photos_proxy.php — Proxy de imagens do Google Drive
 * Busca a imagem server-side (sem restrições de autenticação do browser)
 * e repassa com cache de 1 hora.
 */
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Só aceita IDs que sejam alfanuméricos + _ + -
$id = $_GET['id'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_-]{10,}$/', $id)) {
    http_response_code(400);
    exit('Invalid ID');
}

// Cache no browser por 1 hora
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$url = 'https://lh3.googleusercontent.com/d/' . $id;
$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'follow_location' => 1,
        'max_redirects' => 5,
        'header' => "User-Agent: Mozilla/5.0\r\n",
    ]
]);

$image = @file_get_contents($url, false, $ctx);

if ($image === false || strlen($image) < 100) {
    // Fallback: tenta via uc?export=view
    $url2 = 'https://drive.google.com/uc?export=view&id=' . $id;
    $image = @file_get_contents($url2, false, $ctx);
}

if ($image === false || strlen($image) < 100) {
    http_response_code(404);
    exit;
}

// Detecta tipo MIME básico nos primeiros bytes
$mime = 'image/jpeg';
if (str_starts_with($image, "\x89PNG"))
    $mime = 'image/png';
elseif (str_starts_with($image, "GIF"))
    $mime = 'image/gif';
elseif (str_starts_with($image, "RIFF"))
    $mime = 'image/webp';

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($image));
echo $image;
