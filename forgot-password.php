<?php
/**
 * =====================================================
 * RECUPERAR SENHA - KartOps
 * =====================================================
 */
require_once 'config/config.php';
require_once 'includes/mail_helper.php';
require_once 'includes/csrf.php';

$step = $_GET['step'] ?? 'email';
$error = '';
$message = '';

// Gerar CSRF token e campo HTML
$csrfField = generateCsrfField();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Sessão expirada ou inválida. Por favor, tente novamente.';
    } elseif ($step === 'email') {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if ($email) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE usuarios SET recovery_token = ?, recovery_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
                $stmt->execute([$token, $user['id']]);

                $result = sendPasswordResetEmail($email, $token);
                if ($result['sent']) {
                    $message = 'Enviamos um link de recuperação para o seu e-mail. Verifique a caixa de entrada.';
                } else {
                    $error = 'Erro ao enviar e-mail. ' . ($result['error'] ?? 'Tente novamente mais tarde.');
                }
            } else {
                // Mensagem genérica por segurança
                $message = 'Enviamos as instruções se o e-mail estiver cadastrado.';
            }
        }
    } elseif ($step === 'reset') {
        $token = $_GET['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif ($password !== $confirm) {
            $error = 'As senhas não coincidem.';
        } else {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE recovery_token = ? AND recovery_expiry > NOW() AND ativo = 1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ?, recovery_token = NULL, recovery_expiry = NULL WHERE id = ?");
                $stmt->execute([$hash, $user['id']]);
                $message = 'Senha redefinida com sucesso! Você já pode fazer login.';
            } else {
                $error = 'Token inválido ou expirado.';
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
    <title>Recuperar Senha - KartOps</title>
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
            <a href="index.php" class="auth-back-btn" title="Voltar">
                <i class="fas fa-arrow-left"></i>
            </a>

            <?php if ($step === 'email'): ?>
                <h1 class="auth-title">Recuperar Senha</h1>
                <p class="auth-subtitle">Digite seu e-mail e enviaremos as instruções para você criar uma nova senha.</p>

                <?php if ($error): ?>
                    <div class="auth-alert auth-alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="auth-alert auth-alert-success"><i class="fas fa-info-circle"></i> <?= $message ?></div>
                <?php endif; ?>

                <?php if (!$message || $error): ?>
                    <form method="POST">
                        <?= $csrfField ?>
                        <div class="auth-form-group">
                            <label class="auth-label">E-mail cadastrado</label>
                            <div class="auth-input-wrapper">
                                <input type="email" name="email" class="auth-input" placeholder="seu@email.com" required>
                                <i class="fas fa-envelope auth-input-icon"></i>
                            </div>
                        </div>
                        <button type="submit" class="auth-btn">ENVIAR INSTRUÇÕES</button>
                    </form>
                <?php endif; ?>

            <?php elseif ($step === 'reset'): ?>
                <h1 class="auth-title">Nova Senha</h1>
                <p class="auth-subtitle">Crie uma senha forte e segura que você consiga lembrar.</p>

                <?php if ($error): ?>
                    <div class="auth-alert auth-alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="auth-alert auth-alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
                    <a href="index.php" class="auth-btn"
                        style="text-decoration:none; display:flex; align-items:center; justify-content:center;">FAZER LOGIN</a>
                <?php else: ?>
                    <form method="POST">
                        <?= $csrfField ?>
                        <div class="auth-form-group">
                            <label class="auth-label">Escolha sua nova senha</label>
                            <div class="auth-input-wrapper" style="margin-bottom:12px;">
                                <input type="password" name="password" class="auth-input" placeholder="Mínimo 8 caracteres"
                                    required>
                                <i class="fas fa-lock auth-input-icon"></i>
                            </div>
                            <div class="auth-input-wrapper">
                                <input type="password" name="confirm_password" class="auth-input" placeholder="Confirme a senha"
                                    required>
                                <i class="fas fa-shield-alt auth-input-icon"></i>
                            </div>
                        </div>
                        <button type="submit" class="auth-btn">REDEFINIR SENHA</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php include 'includes/shared_footer.php'; ?>
    </div>
</body>

</html>