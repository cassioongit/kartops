<!-- Footer -->

<!-- ===== PATROCINADORES BAR ===== -->
<?php
// Load sponsor info from official centralized source if not already loaded
require_once __DIR__ . '/patrocinadores.php';

// Hierarchy for Footer: level => [names], level => logo height in px
$hierarchy = [
    1 => ['names' => ['AINEXT MYRMEX'], 'height' => 52],
    2 => ['names' => ['CARDOSO FUNILARIA', 'NOVA DUTRA MULTIMARCAS', 'GREEN SOLUTIONS', 'KDA RACEWEAR'], 'height' => 36],
    3 => ['names' => ['BOTEQUIM GP KART', 'AUTORADIO PODCAST'], 'height' => 36],
];
?>
<?php if (!isset($hideSponsors) || !$hideSponsors): ?>
    <div class="sponsors-bar">
        <span class="sponsors-bar__label">Patrocinadores</span>

        <?php foreach ($hierarchy as $level => $group): ?>
            <div class="sponsors-bar__row sponsors-bar__row--level<?= $level ?>">
                <?php foreach ($group['names'] as $name):
                    $s = $sponsorInfo[$name] ?? null;
                    if (!$s)
                        continue;
                    $tag = $s['site'] ? 'a' : 'div';
                    $attrs = $s['site'] ? " href=\"{$s['site']}\" target=\"_blank\" rel=\"noopener\"" : '';
                    ?>
                    <<?= $tag ?><?= $attrs ?> class="sponsors-bar__logo-wrap" title="<?= htmlspecialchars($name) ?>">
                        <img src="<?= htmlspecialchars($s['logoBranco'] ?? $s['logo']) ?>" alt="<?= htmlspecialchars($name) ?>"
                            style="height: <?= $s['height'] ?? $group['height'] ?>px; width: auto; object-fit: contain;">
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    .sponsors-bar {
        background: #0a0a0a;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 28px 40px 24px;
        text-align: center;
    }

    .sponsors-bar__label {
        display: block;
        font-family: 'Inter', sans-serif;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.3);
        margin-bottom: 20px;
    }

    .sponsors-bar__row {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 32px;
        margin-bottom: 16px;
    }

    .sponsors-bar__row:last-child {
        margin-bottom: 0;
    }

    .sponsors-bar__logo-wrap {
        display: inline-flex;
        align-items: center;
        opacity: 0.7;
        transition: opacity 0.2s;
        text-decoration: none;
        filter: grayscale(30%);
    }

    .sponsors-bar__logo-wrap:hover {
        opacity: 1;
        filter: none;
    }

    /* Separators between levels */
    .sponsors-bar__row--level2 {
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.04);
    }

    .sponsors-bar__row--level3 {
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.04);
    }

    @media (max-width: 600px) {
        .sponsors-bar {
            padding: 24px 20px 20px;
        }

        .sponsors-bar__row {
            gap: 20px;
        }
    }
</style>

<!-- Copyright -->
<footer style="text-align: center; padding: 20px 20px 30px; background: #0a0a0a;">
    <?php include 'shared_footer.php'; ?>
</footer>

<!-- JavaScript Global -->
<script>
    function csrfFetch(url, options = {}) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!options.headers) options.headers = {};
        if (!(options.headers instanceof Headers)) {
            options.headers['X-CSRF-Token'] = token;
        }
        return fetch(url, options);
    }
</script>
<script src="/js/countdown.js"></script>

<!-- JavaScript adicional da página (se existir) -->
<?php if (isset($additionalJS)): ?>
    <?php foreach ($additionalJS as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>