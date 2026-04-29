<?php
/**
 * =====================================================
 * PATROCINADORES - KartOps
 * =====================================================
 * Configuração dos patrocinadores com logos e sites
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);
// Informações dos patrocinadores
$sponsorInfo = [
    'AINEXT MYRMEX' => [
        'logo' => '/images/logos/sponsors/ainext-logo.png',
        'logoBranco' => '/images/logos/sponsors/ainext-logo-branco.png',
        'site' => 'https://ainext.com.br'
    ],
    'AINEXT' => [
        'logo' => '/images/logos/sponsors/ainext-logo.png',
        'logoBranco' => '/images/logos/sponsors/ainext-logo-branco.png',
        'site' => 'https://ainext.com.br'
    ],
    'NOVA DUTRA MULTIMARCAS' => [
        'logo' => '/images/logos/sponsors/novadutra.png',
        'logoBranco' => '/images/logos/sponsors/novadutra-logo-branco.png',
        'site' => 'https://novadutramultimarcas.com.br'
    ],
    'CARDOSO FUNILARIA' => [
        'logo' => '/images/logos/sponsors/cardoso-logo.png',
        'logoBranco' => '/images/logos/sponsors/cardoso-logo-branco.png',
        'site' => null,
        'whatsapp' => '5511947744337'
    ],
    'BOTEQUIM GP KART' => [
        'logo' => '/images/logos/sponsors/botequimgp-logo.jpg',
        'logoBranco' => '/images/logos/sponsors/botequimgp-logo-branco.png',
        'site' => 'https://www.botequimgpkart.com.br/',
        'height' => 96,
    ],
    'AUTORADIO PODCAST' => [
        'logo' => '/images/logos/sponsors/autoradio-logo.png',
        'logoBranco' => '/images/logos/sponsors/autoradio-logo-branco.png',
        'site' => 'https://autoradiopodcast.com.br',
        'height' => 48,
    ],
    'GREEN SOLUTIONS' => [
        'logo' => '/images/logos/sponsors/greensolutions-logo.png',
        'logoBranco' => '/images/logos/sponsors/greensolutions-logo-branco.png',
        'site' => 'https://gsar.com.br'
    ],
    'KDA RACEWEAR' => [
        'logo' => '/images/logos/sponsors/logo-kda-raceweare-monocromatica.png',
        'logoBranco' => '/images/logos/sponsors/logo-kda-raceweare-white.png',
        'site' => '#'
    ],
    'TBD' => [
        'logo' => '/images/logo-campeonato.png', // Este parece estar na raiz de images
        'logoBranco' => '/images/logo-campeonato.png',
        'site' => null
    ]
];

/**
 * Obtém informações do patrocinador
 * @param string $nome Nome do patrocinador
 * @return array Dados do patrocinador (logo, logoBranco, site)
 */
function getSponsorInfo($nome)
{
    global $sponsorInfo;
    $nomeUpper = strtoupper((string) $nome);
    return $sponsorInfo[$nomeUpper] ?? $sponsorInfo['TBD'];
}

/**
 * Gera URL do Google Maps para um kartódromo
 * @param string $kartodromo Nome do kartódromo
 * @return string URL do Google Maps
 */
function getGoogleMapsUrl($kartodromo)
{
    return 'https://www.google.com/maps/search/' . urlencode($kartodromo);
}
?>