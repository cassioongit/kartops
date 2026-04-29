<?php
/**
 * photos.php — Listagem de etapas e pastas de fotos
 * Lê fotos.txt e exibe as etapas, pastas e links para o Google Drive
 */

$pageTitle = 'Fotos - OsKarteiro';
$additionalCSS = ['/css/home_modern_v3.css'];
require_once 'includes/header.php';

// Parser do fotos.txt hierárquico (suporta ---- data)
function parseFotosParaListagem(string $path): array
{
    if (!file_exists($path))
        return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $result = [];
    $etapaIdx = -1;
    $pastaIdx = -1;

    foreach ($lines as $line) {
        $l = trim($line);
        if ($l === '' || $l[0] === '#')
            continue;

        if (str_starts_with($l, '---- ')) {
            // Data da pasta anterior
            if ($etapaIdx >= 0 && $pastaIdx >= 0) {
                $result[$etapaIdx]['pastas'][$pastaIdx]['data'] = trim(substr($l, 5));
            }
        } elseif (str_starts_with($l, '--- ')) {
            $url = trim(substr($l, 4));
            if ($etapaIdx >= 0 && filter_var($url, FILTER_VALIDATE_URL)) {
                $result[$etapaIdx]['pastas'][] = [
                    'nome' => $result[$etapaIdx]['_pasta'] ?? 'Fotos',
                    'url' => $url,
                    'data' => '',
                ];
                $pastaIdx = count($result[$etapaIdx]['pastas']) - 1;
            }
        } elseif (str_starts_with($l, '-- ')) {
            if ($etapaIdx >= 0) {
                $result[$etapaIdx]['_pasta'] = trim(substr($l, 3));
            }
            $pastaIdx = -1;
        } elseif (str_starts_with($l, '- ')) {
            $etapa = trim(substr($l, 2));
            $result[] = ['etapa' => $etapa, 'pastas' => [], '_pasta' => ''];
            $etapaIdx = count($result) - 1;
            $pastaIdx = -1;
        }
    }
    return $result;
}

$etapas = parseFotosParaListagem(__DIR__ . '/fotos.txt');
?>

<div style="max-width: 900px; margin: 0 auto; padding: 140px 20px 60px; font-family: 'Inter', sans-serif; color: #fff;">

    <div style="margin-bottom: 32px;">
        <a href="home.php" style="color: var(--text-secondary, #a0a0a0); font-size: 13px; text-decoration: none;">
            ← Voltar
        </a>
        <h1 style="font-size: 32px; font-weight: 900; text-transform: uppercase; margin-top: 12px;">
            📷 Fotos
        </h1>
        <p style="color: #a0a0a0; font-size: 14px;">Acervo fotográfico do campeonato, organizado por etapa.</p>
    </div>

    <?php if (empty($etapas)): ?>
        <div style="background: #1c1e22; border-radius: 12px; padding: 40px; text-align: center; color: #666;">
            <p style="font-size: 40px; margin-bottom: 12px;">📷</p>
            <p>Nenhuma etapa encontrada em <code>fotos.txt</code>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($etapas as $entry): ?>
            <div
                style="background: #27282b; border-radius: 14px; padding: 24px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.06);">
                <h2 style="font-size: 18px; font-weight: 800; text-transform: uppercase; margin: 0 0 16px; color: #f8b319;">
                    <?= htmlspecialchars($entry['etapa']) ?>
                </h2>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($entry['pastas'] as $pasta): ?>
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.04); border-radius: 8px; padding: 12px 16px; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                <span style="font-size: 20px;">📁</span>
                                <div>
                                    <span style="font-size: 14px; font-weight: 600; display: block;">
                                        <?= htmlspecialchars($pasta['nome']) ?>
                                    </span>
                                    <?php if (!empty($pasta['data'])): ?>
                                        <span style="font-size: 11px; color: #a0a0a0;">
                                            📅 <?= htmlspecialchars($pasta['data']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars($pasta['url']) ?>" target="_blank" rel="noopener"
                                style="display: inline-flex; align-items: center; gap: 6px; background: #1565c0; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; white-space: nowrap; transition: background 0.2s;"
                                onmouseover="this.style.background='#1976d2'" onmouseout="this.style.background='#1565c0'">
                                Ver no Drive →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>