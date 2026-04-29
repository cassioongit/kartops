<?php
/**
 * =====================================================
 * HEADER - KartOps
 * =====================================================
 * Include global de header para todas as páginas
 * Uso: require_once 'includes/header.php';
 */

/**
 * =====================================================
 * HEADER - KartOps
 * =====================================================
 * Include global de header para todas as páginas
 * Uso: require_once 'includes/header.php';
 */

require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/menu_items.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - KartOps' : 'KartOps - Campeonato de Kart' ?>
    </title>

    <?php if ($usuario && !empty($usuario['avatar_url'])): ?>
        <!-- Preload do Avatar para carregar mais rápido -->
        <link rel="preload" as="image" href="<?= htmlspecialchars($usuario['avatar_url']) ?>">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Global -->
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="/css/loader.css?v=<?= filemtime(__DIR__ . '/../css/loader.css') ?>">

    <!-- CSS adicional da página (se existir) -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <?php
            $cssPath = __DIR__ . '/..' . $css;
            $ver = file_exists($cssPath) ? filemtime($cssPath) : time();
            ?>
            <link rel="stylesheet" href="<?= $css ?>?v=<?= $ver ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- CSRF Token (para uso em chamadas fetch/AJAX) -->
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken()) ?>">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#00f2ea">
    <link rel="apple-touch-icon" href="/images/logo-kartops.png">

    <!-- Meta tags para evitar cache em mobile -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker registrado!', reg))
                    .catch(err => console.log('Erro ao registrar Service Worker', err));
            });
        }
    </script>
</head>

<body>
    <!-- Preloader -->
    <div id="page-preloader" class="preloader">
        <div class="loader-content">
            <div class="kart-spinner">🏎️</div>
            <div class="loading-text">Carregando...</div>
        </div>
    </div>

    <script>
        function hideLoader() {
            const loader = document.getElementById('page-preloader');
            if (loader && !loader.classList.contains('loaded')) {
                loader.classList.add('loaded');
            }
        }

        // Hide on DOM Ready (faster than window.load)
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(hideLoader, 500); // Small delay for smooth transition
        });

        // Fallback: Ensure it hides even if DOMContentLoaded fails or takes too long
        window.addEventListener('load', hideLoader);
        setTimeout(hideLoader, 3000); // Force hide after 3s max
    </script>
    <!-- Header -->
    <header>
        <div class="header-content">
            <a href="home.php" class="logo" style="display: flex; align-items: center; padding: 0;">
                <img src="/images/logo-campeonato.png" alt="KartOps" style="max-height: 81px; width: auto;">
            </a>

            <!-- Menu do Usuário (Hamburger) -->
            <div class="user-menu" id="userMenuContainer">
                <button class="user-avatar-btn" id="userMenuBtn" aria-label="Abrir menu">
                    <div class="user-avatar" style="background: transparent; border: none; color: white;">
                        <i class="fas fa-bars" id="menuIcon" style="font-size: 1.2rem;"></i>
                    </div>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-name">
                            <?= $usuario ? htmlspecialchars($usuario['nome']) : 'Convidado' ?>
                        </div>
                        <div class="dropdown-user-email">
                            <?= $usuario ? htmlspecialchars($usuario['email']) : 'Acesse sua conta' ?>
                        </div>
                    </div>
                    <ul class="dropdown-menu">
                        <?php
                        $userRole = $usuario['role'] ?? 'visitante';
                        $isVisitor = isset($_SESSION['is_guest']);
                        $isHardGuest = !$usuario;
                    
                        foreach ($menuItems as $item) {
                            if (isset($item['divider'])) {
                                echo '<li class="dropdown-divider"></li>';
                            } else {
                                renderMenuLink($item, $userRole, $isVisitor, $isHardGuest);
                            }
                        }

                        if ($usuario): ?>
                            <li><a href="logout.php" class="dropdown-link logout">🚪 Sair</a></li>
                        <?php else: ?>
                            <li><a href="index.php" class="dropdown-link">🔑 Login</a></li>
                            <li><a href="register.php" class="dropdown-link">✨ Criar Conta</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const userMenu = document.getElementById('userMenuContainer');
                const userMenuBtn = document.getElementById('userMenuBtn');
                const menuIcon = document.getElementById('menuIcon');

                // Toggle Menu
                if (userMenuBtn) {
                    userMenuBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        userMenu.classList.toggle('active');
                        
                        if (userMenu.classList.contains('active')) {
                            menuIcon.classList.remove('fa-bars');
                            menuIcon.classList.add('fa-xmark');
                        } else {
                            menuIcon.classList.remove('fa-xmark');
                            menuIcon.classList.add('fa-bars');
                        }
                    });
                }

                // Fechar ao clicar fora
                document.addEventListener('click', (e) => {
                    if (userMenu && userMenu.classList.contains('active') && !userMenu.contains(e.target)) {
                        userMenu.classList.remove('active');
                        menuIcon.classList.remove('fa-xmark');
                        menuIcon.classList.add('fa-bars');
                    }
                });
            });
        </script>
    </header>