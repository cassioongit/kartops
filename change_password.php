<?php
/**
 * =====================================================
 * ALTERAR SENHA - KartOps
 * =====================================================
 * Permite ao usuário logado alterar sua senha
 */

// Configurações da página
$pageTitle = 'Alterar Senha';
$additionalCSS = ['/css/auth_modern.css'];

$error = '';
$success = '';

// O header.php já inicia sessão e busca $usuario
require_once 'includes/header.php';

// Verificar se usuário está logado e NÃO é visitante
if (!$usuario) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

if (isset($_SESSION['is_guest']) && $_SESSION['is_guest']) {
    echo "<script>window.location.href='perfil.php';</script>";
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validações básicas
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Por favor, preencha todos os campos.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'A nova senha e a confirmação não coincidem.';
    } elseif (strlen($new_password) < 8) {
        $error = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($current_password === $new_password) {
        $error = 'A nova senha deve ser diferente da atual.';
    } else {
        // Buscar hash da senha atual no banco
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        $stored_hash = $stmt->fetchColumn();

        if ($stored_hash && password_verify($current_password, $stored_hash)) {
            // Senha atual correta -> Atualizar
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ?, atualizado_em = NOW() WHERE id = ?");
            if ($updateStmt->execute([$new_hash, $usuario['id']])) {
                $success = 'Senha alterada com sucesso!';

                // Notificar Admin
                sendAdminNotification(
                    "Senha Alterada (Perfil): " . $usuario['nome'],
                    "O usuário <strong>" . $usuario['nome'] . "</strong> (" . $usuario['email'] . ") alterou a senha pelo perfil."
                );

                // Limpar campos
                $_POST = [];
            } else {
                $error = 'Erro ao atualizar a senha no banco de dados.';
            }
        } else {
            $error = 'A senha atual está incorreta.';
        }
    }
}

?>




<div class="auth-wrapper">
    <div class="auth-card">
        <a href="perfil.php" class="auth-back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="auth-illustration">
            <!-- SVG para o Cadeado com Chave (estilo imagem 1) -->
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <rect x="50" y="80" width="100" height="80" rx="15" fill="#ffb74d" />
                <path d="M70 80V60C70 43.4 83.4 30 100 30C116.6 30 130 43.4 130 60V80" stroke="#ffb74d"
                    stroke-width="12" fill="none" />
                <circle cx="100" cy="120" r="12" fill="#2c3e50" />
                <path d="M100 132V145" stroke="#2c3e50" stroke-width="8" stroke-linecap="round" />
                <!-- Representação da Chave -->
                <circle cx="135" cy="115" r="10" fill="#2c3e50" />
                <rect x="110" cy="112" width="25" height="6" fill="#2c3e50" />
            </svg>
        </div>

        <h1 class="auth-title">Alterar Senha</h1>
        <p class="auth-subtitle">Sua nova senha deve ser diferente da anterior para garantir sua segurança.</p>

        <?php if ($error): ?>
            <div class="auth-alert auth-alert-error">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert-success">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= generateCsrfField() ?>

            <div class="auth-form-group">
                <div class="auth-input-wrapper">
                    <input type="password" name="current_password" class="auth-input" placeholder="Senha Atual"
                        required>
                    <i class="fas fa-eye auth-input-icon"></i>
                </div>
            </div>

            <div class="auth-form-group">
                <div class="auth-input-wrapper">
                    <input type="password" name="new_password" class="auth-input" placeholder="Nova Senha" required
                        minlength="8">
                    <i class="fas fa-lock auth-input-icon"></i>
                </div>
            </div>

            <div class="auth-form-group">
                <div class="auth-input-wrapper">
                    <input type="password" name="confirm_password" class="auth-input" placeholder="Confirmar Senha"
                        required>
                    <i class="fas fa-shield-alt auth-input-icon"></i>
                </div>
            </div>

            <button type="submit" class="auth-btn">CONFIRMAR ALTERAÇÃO</button>
        </form>

        <div class="auth-footer-links">
            <a href="perfil.php" class="auth-link">Voltar para o Perfil</a>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>