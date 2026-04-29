<?php
/**
 * =====================================================
 * DASHBOARD - KartOps (Admin e Colaborador)
 * Estilo Google Account
 * =====================================================
 */

// Configurações da página
$pageTitle = 'Dashboard';
$additionalCSS = ['/css/dashboard.css'];

// Incluir header
require_once 'includes/header.php';

// Verificar se usuário está logado
if (!$usuario) {
    header('Location: index.php');
    exit;
}

// Verificar se é admin ou colaborador
if ($usuario['role'] !== 'Admin' && $usuario['role'] !== 'Colaborador' && $usuario['role'] !== 'Owner') {
    header('Location: index.php');
    exit;
}

$isAdmin = ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner');
$firstName = htmlspecialchars(explode(' ', $usuario['nome'])[0]);
?>

<!-- Dashboard Content -->
<div class="dashboard-container">

    <!-- Profile Section -->
    <div class="profile-section">
        <div class="profile-avatar" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
            <i class="fas fa-user" style="color: white; font-size: 2rem;"></i>
        </div>
        <div class="profile-info">
            <h1 class="profile-name"><?= htmlspecialchars($usuario['nome']) ?></h1>
            <p class="profile-email"><?= htmlspecialchars($usuario['email']) ?></p>
            <span class="profile-role-badge"><?= htmlspecialchars($usuario['role']) ?></span>
        </div>
    </div>

    <!-- Perfil & Conta -->
    <div class="section-group">
        <a href="perfil.php" class="nav-item">
            <div class="nav-icon icon-blue">
                <i class="fas fa-user"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Meu Perfil</div>
                <div class="nav-description">Visualizar e editar seu perfil</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="change_password.php" class="nav-item">
            <div class="nav-icon icon-green">
                <i class="fas fa-lock"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Segurança</div>
                <div class="nav-description">Alterar senha e configurações de acesso</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>
    </div>

    <!-- Campeonato -->
    <div class="section-group">
        <a href="home.php" class="nav-item">
            <div class="nav-icon icon-teal">
                <i class="fas fa-home"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Página Inicial</div>
                <div class="nav-description">Voltar para a página principal</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="etapas.php" class="nav-item">
            <div class="nav-icon icon-amber">
                <i class="fas fa-flag-checkered"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Etapas</div>
                <div class="nav-description">Gerenciar etapas do campeonato</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="resultados.php" class="nav-item">
            <div class="nav-icon icon-pink">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Resultados</div>
                <div class="nav-description">Lançar e visualizar resultados</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="classificacao.php" class="nav-item">
            <div class="nav-icon icon-cyan">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Classificação</div>
                <div class="nav-description">Ranking de pilotos e equipes</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="onboards.php" class="nav-item">
            <div class="nav-icon icon-red">
                <i class="fas fa-video"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Onboards</div>
                <div class="nav-description">Vídeos das corridas</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>
    </div>

    <!-- Administração -->
    <div class="section-group">
        <a href="pilotos.php" class="nav-item">
            <div class="nav-icon icon-orange">
                <i class="fas fa-helmet-safety"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Pilotos</div>
                <div class="nav-description">Gerenciar pilotos cadastrados</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="equipes.php" class="nav-item">
            <div class="nav-icon icon-purple">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Equipes</div>
                <div class="nav-description">Gerenciar equipes</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="categorias.php" class="nav-item">
            <div class="nav-icon icon-indigo">
                <i class="fas fa-tags"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Categorias</div>
                <div class="nav-description">Gerenciar categorias do campeonato</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <a href="pontuacao.php" class="nav-item">
            <div class="nav-icon icon-light-green">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Pontuação</div>
                <div class="nav-description">Regras e tabela de pontuação</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>

        <?php if ($isAdmin): ?>
            <a href="usuarios.php" class="nav-item">
                <div class="nav-icon icon-orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="nav-text">
                    <div class="nav-title">Usuários</div>
                    <div class="nav-description">Gerenciar usuários do sistema</div>
                </div>
                <span class="nav-arrow">›</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Sair -->
    <div class="section-group">
        <a href="logout.php" class="nav-item logout-item">
            <div class="nav-icon icon-red">
                <i class="fas fa-right-from-bracket"></i>
            </div>
            <div class="nav-text">
                <div class="nav-title">Sair</div>
                <div class="nav-description">Fazer logout do sistema</div>
            </div>
            <span class="nav-arrow">›</span>
        </a>
    </div>

</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>