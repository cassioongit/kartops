<?php
/**
 * Shared Menu Items
 * This file centralizes the menu links to ensure consistency across Registered and Visitor views.
 */

// Define menu structure
$menuItems = [
    ['icon' => '🏠', 'label' => 'Página Inicial', 'url' => 'home.php', 'min_role' => 'visitante'],
    ['icon' => '👤', 'label' => 'Perfil', 'url' => 'perfil.php', 'min_role' => 'visitante'],
    ['divider' => true],
    ['icon' => '🏎️', 'label' => 'Pilotos', 'url' => 'pilotos.php', 'min_role' => 'visitante'],
    ['icon' => '🛡️', 'label' => 'Equipes', 'url' => 'equipes.php', 'min_role' => 'visitante'],
    ['icon' => '📊', 'label' => 'Classificação', 'url' => 'classificacao.php', 'min_role' => 'visitante'],
    ['icon' => '🎬', 'label' => 'Onboards', 'url' => 'onboards.php', 'min_role' => 'visitante'],
    ['icon' => '🏆', 'label' => 'Resultados', 'url' => 'resultados.php', 'min_role' => 'visitante'],
    ['divider' => true],
    ['icon' => '🏁', 'label' => 'Etapas', 'url' => 'etapas.php', 'min_role' => 'visitante'],
    ['icon' => '🏷️', 'label' => 'Categorias', 'url' => 'categorias.php', 'min_role' => 'Admin'],
    ['icon' => '🔢', 'label' => 'Pontuação', 'url' => 'pontuacao.php', 'min_role' => 'Admin'],
    ['divider' => true],
    ['icon' => '📊', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'min_role' => 'Colaborador'],
    ['icon' => '👥', 'label' => 'Usuários', 'url' => 'usuarios.php', 'min_role' => 'Admin'],
    ['divider' => true],
];

/**
 * Helper to render a menu link
 */
function renderMenuLink($item, $userRole, $isVisitor, $isHardGuest)
{
    $rolePriority = [
        'visitante' => 0,
        'Piloto' => 1,
        'Colaborador' => 2,
        'Admin' => 3,
        'Owner' => 4
    ];

    $minRole = $item['min_role'] ?? 'visitante';
    $userPriority = $rolePriority[$userRole] ?? 1; // Default register priority
    $minPriority = $rolePriority[$minRole] ?? 0;

    // Hard guests (not logged in at all) see everything disabled
    if ($isHardGuest) {
        $hasAccess = false;
    } else {
        // Regular access logic:
        // 1. Role priority must match
        // 2. Visitors (guests) only see 'visitante' level items
        $hasAccess = ($userPriority >= $minPriority) && (!$isVisitor || $minRole === 'visitante');
    }

    if ($hasAccess) {
        echo '<li><a href="' . $item['url'] . '" class="dropdown-link">' . $item['icon'] . ' ' . $item['label'] . '</a></li>';
    } else {
        echo '<li><span class="dropdown-link dropdown-link--disabled">' . $item['icon'] . ' ' . $item['label'] . '</span></li>';
    }
}
