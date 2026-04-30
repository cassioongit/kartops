<?php
require_once 'config/config.php';
if (!isset($_GET['code'])) {
    // Redireciona com erro
    header('Location: index.php?error=Processo+cancelado+ou+erro+na+autenticacao');
    exit;
}

if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['csrf_token']) {
    // Possível ataque CSRF
    header('Location: index.php?error=Sessao+expirada+ou+token+invalido');
    exit;
}

// 1. Obter o token de acesso
$token_url = 'https://oauth2.googleapis.com/token';
$post_data = [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
$response = curl_exec($ch);

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    header('Location: index.php?error=Falha+ao+obter+credenciais+do+Google');
    exit;
}

$access_token = $token_data['access_token'];

// 2. Obter dados do usuário
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
$user_info_response = curl_exec($ch);

$user_info = json_decode($user_info_response, true);

if (!isset($user_info['email'])) {
    header('Location: index.php?error=Falha+ao+obter+dados+do+usuario');
    exit;
}

$email = $user_info['email'];
$name = $user_info['name'] ?? 'Usuário Google';
$avatar = $user_info['picture'] ?? null;

$pdo = getDBConnection();

// 3. Verificar se o usuário já existe
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    if ($user['ativo'] == 0) {
        header('Location: index.php?error=Esta+conta+esta+desativada');
        exit;
    }

    // Atualizar o avatar caso o usuário logue pelo Google e ainda não tenha foto (ou para manter atualizado)
    if ($avatar && empty($user['avatar_url'])) {
        $upd = $pdo->prepare("UPDATE usuarios SET avatar_url = ? WHERE id = ?");
        $upd->execute([$avatar, $user['id']]);
        $user['avatar_url'] = null;
    }

    $user_id = $user['id'];
    $user_role = $user['role'];
} else {
    // 4. Cadastrar novo usuário (Pode registrar direto ou redirecionar para uma etapa de confirmação, optamos por direto aqui)
    $random_password = bin2hex(random_bytes(10));
    $senha_hash = password_hash($random_password, PASSWORD_DEFAULT);

    // Gerar UUID para o novo usuário
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );

    $stmt = $pdo->prepare("INSERT INTO usuarios (id, nome, email, senha_hash, role, ativo, avatar_url) VALUES (?, ?, ?, ?, 'Usuário', 1, NULL)");
    $stmt->execute([$uuid, $name, $email, $senha_hash]);
    $user_id = $uuid;
    $user_role = 'Usuário';
}

// 5. Iniciar sessão
session_regenerate_id(true);
$sessionToken = session_id();

$updateStmt = $pdo->prepare("UPDATE usuarios SET session_token = ? WHERE id = ?");
$updateStmt->execute([$sessionToken, $user_id]);

$_SESSION['user_id'] = $user_id;
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $user['nome'] ?? $name;
$_SESSION['user_role'] = $user_role;
$_SESSION['session_token'] = $sessionToken;

if (isset($_SESSION['login_email'])) {
    unset($_SESSION['login_email']);
}

session_write_close();

header('Location: home.php');
exit;
