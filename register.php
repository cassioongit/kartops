<?php
/**
 * =====================================================
 * REGISTRO DE USUÁRIO - KartOps
 * =====================================================
 */

require_once 'config/config.php';

// Configurar persistência (30 dias)
$lifetime = 3600 * 24 * 30; // 30 dias
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Gerar CSRF token se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';

require_once 'includes/mail_helper.php';
require_once 'includes/csrf.php';
require_once 'includes/image_helper.php';

$error = '';
$success = '';

// Campos do formulário
$nome = '';
$email = '';
$telefone = '';
$contato_emergencia_nome = '';
$contato_emergencia_telefone = '';
$avatar_url = '';
$optin = 1;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Sessão expirada ou inválida. Por favor, tente novamente.';
    } else {
        $nome = trim($_POST['nome']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $telefone = trim($_POST['telefone'] ?? '');
        $contato_emergencia_nome = trim($_POST['contato_emergencia_nome'] ?? '');
        $contato_emergencia_telefone = trim($_POST['contato_emergencia_telefone'] ?? '');
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        $avatar_url = trim($_POST['avatar_url'] ?? '');
        $optin = isset($_POST['optin']) ? 1 : 0;

        $avatar_url = null; // Usuários nâo tem mais avatar


        if (empty($error)) {
            if (empty($nome)) {
                $error = 'Por favor, digite seu nome';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'E-mail inválido';
            } elseif (strlen($senha) < 8) {
                $error = 'A senha deve ter no mínimo 8 caracteres';
            } elseif ($senha !== $confirmar_senha) {
                $error = 'As senhas não coincidem';
            } else {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Este e-mail já está cadastrado. <a href="index.php" style="color:white;text-decoration:underline;">Fazer login?</a>';
                    } else {
                        $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
                        $hash = password_hash($senha, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, contato_emergencia_nome, contato_emergencia_telefone, avatar_url, optin_news, role, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Usuário', 1)");
                        $stmt->execute([$userId, $nome, $email, $hash, $telefone, $contato_emergencia_nome, $contato_emergencia_telefone, $avatar_url, $optin]);

                        sendWelcomeEmail($email, $nome);
                        $success = 'Conta criada com sucesso! Redirecionando...';

                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $nome;
                        $_SESSION['user_role'] = 'Usuário';

                        echo "<script>setTimeout(() => { window.location.href = 'home.php'; }, 2000);</script>";
                    }
                } catch (PDOException $e) {
                    $error = 'Erro ao criar conta: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Criar Conta - KartOps</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth_standard.css">
</head>

<body>
    <div class="auth-main-container">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="/images/logo-kartops.png" alt="KartOps Logo">
            </div>
            <?php if (!$success): ?>
                <a href="index.php" class="auth-back-btn" title="Voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php endif; ?>

            <h1 class="auth-title"><?= $success ? 'Sucesso!' : 'Criar nova conta' ?></h1>
            <p class="auth-subtitle"><?= $success ? $success : 'Junte-se à maior comunidade de Kart' ?></p>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form action="register.php" method="POST" enctype="multipart/form-data">
                    <?= $csrfField ?>

                    <div class="auth-form-group">
                        <label class="auth-label" for="nome">Nome completo</label>
                        <div class="auth-input-wrapper">
                            <input type="text" id="nome" name="nome" class="auth-input"
                                value="<?= htmlspecialchars($nome) ?>" placeholder="Seu nome" required autocomplete="name">
                            <i class="fas fa-user auth-input-icon"></i>
                        </div>
                    </div>

                    <div class="auth-form-group">
                        <label class="auth-label" for="email">E-mail</label>
                        <div class="auth-input-wrapper">
                            <input type="email" id="email" name="email" class="auth-input"
                                value="<?= htmlspecialchars($email) ?>" placeholder="Seu e-mail" required
                                autocomplete="email">
                            <i class="fas fa-envelope auth-input-icon"></i>
                        </div>
                    </div>



                    <div class="auth-form-group">
                        <label class="auth-label" for="telefone">WhatsApp</label>
                        <div class="auth-input-wrapper">
                            <input type="tel" id="telefone" name="telefone" class="auth-input"
                                value="<?= htmlspecialchars($telefone) ?>" placeholder="(00) 00000-0000">
                            <i class="fab fa-whatsapp auth-input-icon"></i>
                        </div>
                    </div>

                    <div
                        style="margin: 24px 0 12px; color: var(--auth-text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; text-align: left; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
                        Segurança (Emergência)
                    </div>

                    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                        <input type="text" name="contato_emergencia_nome" class="auth-input" style="flex: 1.5;"
                            placeholder="Aviso a...">
                        <input type="tel" name="contato_emergencia_telefone" class="auth-input" style="flex: 1;"
                            placeholder="Tel">
                    </div>

                    <div class="auth-form-group">
                        <label class="auth-label">Senha de acesso</label>
                        <div class="auth-input-wrapper" style="margin-bottom: 12px;">
                            <input type="password" name="senha" class="auth-input" placeholder="Mínimo 8 caracteres"
                                required autocomplete="new-password">
                            <i class="fas fa-lock auth-input-icon"></i>
                        </div>
                        <div class="auth-input-wrapper">
                            <input type="password" name="confirmar_senha" class="auth-input" placeholder="Confirme a senha"
                                required autocomplete="new-password">
                            <i class="fas fa-shield-alt auth-input-icon"></i>
                        </div>
                    </div>

                    <div class="auth-form-group">
                        <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="optin" value="1" <?= $optin ? 'checked' : '' ?>
                                style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--auth-primary);">
                            <span style="font-size: 13px; line-height: 1.5; color: var(--auth-text-muted);">
                                Aceito receber atualizações sobre o campeonato, regras e descontos.
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="auth-btn">Criar minha conta</button>

                    <div class="auth-footer-links">
                        <a href="index.php" class="auth-link">Já tem uma conta? Fazer login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php include 'includes/shared_footer.php'; ?>
    </div>
</body>

</html>