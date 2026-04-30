<?php
/**
 * =====================================================
 * LOGIN - KartOps (2 Etapas)
 * =====================================================
 */

require_once 'config/config.php';


// Gerar CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';

// Google Auth URL
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'state' => $_SESSION['csrf_token']
]);

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

// Login como Visitante
if (isset($_GET['action']) && $_GET['action'] === 'visitor') {
    $_SESSION['user_id'] = 'visitor_' . uniqid();
    $_SESSION['user_email'] = 'visitante@KartOps.com';
    $_SESSION['user_name'] = 'Lentidão Jr.';
    $_SESSION['user_role'] = 'visitante';
    $_SESSION['user_avatar'] = 'images/turtle-avatar.png';
    $_SESSION['is_guest'] = true;

    header('Location: home.php');
    exit;
}

$step = $_GET['step'] ?? 'email';
$error = $_GET['error'] ?? $_GET['msg'] ?? '';
$email = $_SESSION['login_email'] ?? '';

// Rate Limiting Básico de Login
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = 'login_attempts_' . md5($ip);
$rate_limit_time_key = 'login_attempts_time_' . md5($ip);

$attempts = $_SESSION[$rate_limit_key] ?? 0;
$last_attempt = $_SESSION[$rate_limit_time_key] ?? 0;

if (time() - $last_attempt > 900) {
    $attempts = 0;
    $_SESSION[$rate_limit_key] = 0;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($attempts >= 5) {
        $error = 'Muitas tentativas falhas. Aguarde 15 minutos.';
    } else {
        require_once 'includes/csrf.php';
        if (!validateCsrfToken()) {
            $error = 'Sessão expirada ou inválida. Por favor, tente novamente.';
        } elseif (isset($_POST['email'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['login_email'] = $email;
                header('Location: index.php?step=password');
                exit;
            } else {
                $error = 'Email inválido';
            }
        } elseif (isset($_POST['password'])) {
            $password = $_POST['password'];
            $email = $_SESSION['login_email'] ?? '';
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['senha_hash'])) {
                $_SESSION[$rate_limit_key] = 0;
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_role'] = $user['role'];
                unset($_SESSION['login_email']);
                header('Location: home.php');
                exit;
            } else {
                $_SESSION[$rate_limit_key] = $attempts + 1;
                $_SESSION[$rate_limit_time_key] = time();
                $error = 'Senha incorreta ou usuário não encontrado.';
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
    <title>Login - KartOps</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth_standard.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#00f2ea">
</head>

<body>
    <!-- Banner Informativo Google Login -->
    <div id="googleBanner" class="google-banner">
        <i class="fab fa-google"></i>
        <span>Login com Google já está disponível!</span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const banner = document.getElementById('googleBanner');
                if (banner) {
                    banner.style.animation = 'fadeOut 0.5s ease forwards';
                    setTimeout(() => banner.remove(), 500);
                }
            }, 3000);
        });
    </script>

    <div class="auth-main-container">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="/images/logo-kartops.png" alt="KartOps Logo">
            </div>

            <?php if ($step === 'password'): ?>
                <a href="index.php" class="auth-back-btn"><i class="fas fa-arrow-left"></i></a>
            <?php endif; ?>

            <h1 class="auth-title">Bem-vindo</h1>
            <p class="auth-subtitle">Sistema KartOps de gerenciamento de campeonatos.</p>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-error"><i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= $csrfField ?>
                <?php if ($step === 'email'): ?>
                    <div class="auth-form-group">
                        <label class="auth-label">E-mail</label>
                        <div class="auth-input-wrapper">
                            <input type="email" name="email" class="auth-input" placeholder="seu@email.com"
                                value="<?= htmlspecialchars($email) ?>" required>
                            <i class="fas fa-envelope auth-input-icon"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-form-group">
                        <label class="auth-label">Acesso para <?= htmlspecialchars($email) ?></label>
                        <div class="auth-input-wrapper">
                            <input type="password" name="password" class="auth-input" placeholder="Sua senha" required>
                            <i class="fas fa-lock auth-input-icon"></i>
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="auth-btn">Continuar</button>
            </form>

            <div
                style="margin: 24px 0 16px; display: flex; align-items: center; gap: 10px; color: var(--auth-text-muted); font-size: 13px;">
                <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
                ou continue com
                <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
            </div>

            <div style="margin-bottom: 24px;">
                <a href="<?= htmlspecialchars($google_login_url) ?>" class="auth-extra-btn auth-btn-google">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            fill="#4285F4" />
                        <path
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            fill="#34A853" />
                        <path
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                            fill="#FBBC05" />
                        <path
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                            fill="#EA4335" />
                    </svg>
                    Continuar com Google
                </a>
                <a href="index.php?action=visitor" class="auth-extra-btn auth-btn-visitor">
                    <i class="fas fa-user-secret"></i>
                    CONTINUAR SEM SENHA
                </a>
            </div>

            <div class="auth-footer-links">
                <a href="register.php" class="auth-link">Não tem conta? Criar agora</a>
                <br><br>
                <a href="forgot-password.php" class="auth-link" style="font-weight: normal; font-size: 13px;">Esqueceu a
                    senha?</a>
            </div>
        </div>

        <?php include 'includes/shared_footer.php'; ?>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
</body>

</html>