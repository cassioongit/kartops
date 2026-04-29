<?php
/**
 * =====================================================
 * RENDERIZAÇÃO DE PATROCINADORES - KartOps
 * =====================================================
 * Função centralizada para renderizar seção de patrocinadores
 * Usada em TODAS as páginas com estilo e tamanhos IDÊNTICOS
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);

// Incluir CSS de patrocinadores automaticamente (apenas uma vez)
if (!defined('SPONSORS_CSS_LOADED')) {
    define('SPONSORS_CSS_LOADED', true);
    echo '<link rel="stylesheet" href="/css/sponsors.css">';
}

/**
 * Renderiza a seção completa de patrocinadores
 * @param string $titleEmoji Emoji para o título (opcional)
 */
function renderSponsorsSection($titleEmoji = '')
{
    // Tiers de patrocinadores (Baseado na importância)
    $tier1 = ['AINEXT MYRMEX'];
    $tier2 = ['CARDOSO FUNILARIA', 'NOVA DUTRA MULTIMARCAS', 'GREEN SOLUTIONS', 'KDA RACEWEAR'];
    $tier3 = ['AUTORADIO PODCAST', 'BOTEQUIM GP KART'];

    echo '<section class="sponsors-section">';
    echo '<h2 class="sponsors-title">' . $titleEmoji . 'Patrocinadores</h2>';
    echo '<div class="sponsors-container">';

    renderSponsorTier($tier1, 'tier-1');
    renderSponsorTier($tier2, 'tier-2');
    renderSponsorTier($tier3, 'tier-3');

    echo '</div>';
    echo '</section>';
}

/**
 * Renderiza um tier de patrocinadores
 * @param array $tierList Lista de nomes dos patrocinadores
 * @param string $tierClass Classe do tier (tier-1, tier-2, tier-3)
 */
function renderSponsorTier($tierList, $tierClass)
{
    echo '<div class="sponsor-tier ' . $tierClass . '">';
    foreach ($tierList as $name) {
        $sponsor = getSponsorInfo($name);
        $logo = $sponsor['logo'];

        if ($sponsor['site']) {
            echo '<a href="' . htmlspecialchars($sponsor['site']) . '" target="_blank" rel="noopener noreferrer" class="sponsor-card">';
            echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($name) . '">';
            echo '</a>';
        } else {
            echo '<div class="sponsor-card">';
            echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($name) . '">';
            echo '</div>';
        }
    }
    echo '</div>';
}
?>