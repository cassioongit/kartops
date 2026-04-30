<?php
/**
 * =====================================================
 * INDEX - KartOps (v3 Dark Redesign) 
 * =====================================================
 */

$pageTitle = 'Home';
$additionalCSS = ['/css/home_modern_v3.css'];

require_once 'config/config.php';
require_once 'includes/patrocinadores.php';
require_once 'includes/classificacao_helper.php';

try {
    $pdo = getDBConnection();

    // 1. Próxima Etapa
    $stmt = $pdo->prepare("SELECT * FROM etapas WHERE CONCAT(data, ' ', hora) >= NOW() ORDER BY data ASC, hora ASC LIMIT 1");
    $stmt->execute();
    $nextEvent = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Classificações para Ranking (Master e Challengers)
    $masterData = getClassificationData($pdo, 'Master');
    $challengerData = getClassificationData($pdo, 'Challenger');

    $topPilotsMaster = array_slice($masterData, 0, 8);
    $topPilotsChallenger = array_slice($challengerData, 0, 8);

    // 3. Equipes
    $teamMasterData = getTeamClassificationData($pdo, 'Master');
    $topTeamMaster = $teamMasterData[0] ?? null;
    $teamResults = array_slice($teamMasterData, 0, 5);

    $teamChallengerData = getTeamClassificationData($pdo, 'Challenger');
    $topTeamChallenger = $teamChallengerData[0] ?? null;
    $teamResultsChallenger = array_slice($teamChallengerData, 0, 5);

    // 4. Todas as Etapas (The Grid)
    $stmtStages = $pdo->query("SELECT * FROM etapas ORDER BY data ASC");
    $allStages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);
    $upcomingStages = array_filter($allStages, fn($s) => strtotime($s['data']) >= time());
    $pastStages = array_filter($allStages, fn($s) => strtotime($s['data']) < time());

    // 5. Meet the Pilots (Alguns pilotos em destaque)
    $stmtPilotos = $pdo->query("SELECT * FROM pilotos WHERE foto IS NOT NULL AND foto != '' ORDER BY RAND() LIMIT 12");
    $featuredPilots = $stmtPilotos->fetchAll(PDO::FETCH_ASSOC);

    // 6. Conheça as Equipes (Equipe aleatória)
    $stmtTeam = $pdo->query("SELECT * FROM equipes ORDER BY RAND() LIMIT 1");
    $randomTeam = $stmtTeam->fetch(PDO::FETCH_ASSOC);
    $teamHighlight = null;

    if ($randomTeam) {
        $teamId = $randomTeam['id'];
        
        // Chefe
        $stmtChief = $pdo->prepare("SELECT nome FROM pilotos WHERE id = ?");
        $stmtChief->execute([$randomTeam['chefe'] ?? null]);
        $chiefName = $stmtChief->fetchColumn() ?: 'N/A';

        // Melhor Master e Challenger da equipe usando classificacao_helper
        // Já temos $masterData e $challengerData carregados acima
        
        $bestMaster = null;
        $bestChallenger = null;

        foreach ($masterData as $p) {
            if (($p['equipe_id'] ?? null) == $teamId) {
                $bestMaster = $p;
                break; // masterData já vem ordenado por pontos
            }
        }

        foreach ($challengerData as $p) {
            if (($p['equipe_id'] ?? null) == $teamId) {
                $bestChallenger = $p;
                break; // challengerData já vem ordenado por pontos
            }
        }

        $teamHighlight = [
            'id' => $teamId,
            'nome' => $randomTeam['nome'],
            'foto' => $randomTeam['imagem'] ?? null,
            'cor' => $randomTeam['cor'] ?: '#1a2535',
            'chefe' => $chiefName,
            'top_master' => $bestMaster ? $bestMaster['nome'] : '---',
            'top_challenger' => $bestChallenger ? $bestChallenger['nome'] : '---'
        ];
    }

    // 7. Patrocinador do Mês
    $sponsorModalData = null;
    $sponsorJsonPath = __DIR__ . '/config/patrocinador_mes.json';
    if (file_exists($sponsorJsonPath)) {
        $jsonContent = file_get_contents($sponsorJsonPath);
        $parsedConfig = json_decode($jsonContent, true);
        if ($parsedConfig && isset($parsedConfig['ativo']) && $parsedConfig['ativo']) {
            // Verifica logica de visitantes e cookies
            if (!isset($_COOKIE['sponsor_seen'])) {
                $sponsorModalData = $parsedConfig;
            }
        }
    }

} catch (Exception $e) {
    error_log("[KartOps] Erro: " . $e->getMessage());
$error = 'Erro interno do servidor. Tente novamente mais tarde.';
}

require_once 'includes/header.php';
?>

<div class="home-grid-container">

    <?php if ($teamHighlight): ?>
        <style>
            body {
                background: radial-gradient(circle at center, <?= $teamHighlight['cor'] ?>cc 0%, #000 100%) !important;
                background-attachment: fixed !important;
            }
        </style>
    <?php endif; ?>

    <?php if (isset($sponsorModalData)): ?>
        <script>window.pendingSponsorModal = true;</script>
    <?php endif; ?>

    <!-- SAUDAÇÃO -->
    <?php
    $primeiroNome = $usuario ? explode(' ', $usuario['nome'])[0] : 'Piloto';
    $hora = (int) date('H');
    $saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
    ?>
    <div class="greeting-bar">
        <div>
            <h2 class="greeting-title"><?= $saudacao ?>, <?= htmlspecialchars($primeiroNome) ?> 👋</h2>
            <p class="greeting-sub">Bem-vindo ao KartOps!</p>
        </div>
    </div>

    <?php if (isset($_SESSION['is_guest']) && $_SESSION['is_guest']): ?>
        <div id="visitor-toast"
            style="position: fixed; top: 80px; right: 20px; z-index: 9999; background: #1a2535; border-left: 4px solid #f87171; border-radius: 8px; padding: 16px 20px; max-width: 320px; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); transform: translateX(120%); transition: transform 0.3s ease-out;">
            <div style="font-size: 24px; line-height: 1; margin-top: 2px;">🎭</div>
            <div>
                <h4 style="color: #f87171; margin: 0 0 5px 0; font-size: 15px; font-weight: 600;">Modo Visitante</h4>
                <p style="color: #d1d5db; margin: 0; font-size: 13px; line-height: 1.4;">
                    Olá Lentidão ! Lembre-se que você não terá acesso a páginas de Resultados, Não poderá subir onboards
                    ou executar qualquer alteração em seu perfil de usuario e piloto até que possua um login oficial.
                </p>
            </div>
        </div>
        <script>
            window.showVisitorToast = function () {
                const toast = document.getElementById('visitor-toast');
                if (toast) {
                    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
                    setTimeout(() => {
                        toast.style.transform = 'translateX(120%)';
                        setTimeout(() => toast.remove(), 300);
                    }, 8000);
                }
            };
            document.addEventListener("DOMContentLoaded", () => {
                // Se não vai abrir modal de patrocinador, mostra o toast agora
                if (window.pendingSponsorModal !== true) {
                    window.showVisitorToast();
                }
            });
        </script>
    <?php endif; ?>

    <!-- COLUNA 1 -->
    <div class="grid-column">

        <!-- SECTION 1: HERO EVENTO -->
        <section class="hero-section bento-card">
            <span class="section-label">PRÓXIMA ETAPA</span>


            <!-- Row: Sponsor Logo (Top) -->
            <div class="sponsor-mini" style="display: flex; justify-content: center; width: 100%; margin-bottom: 20px;">
                <?php if ($nextEvent):
                    $sInfo = getSponsorInfo($nextEvent['patrocinador']); ?>
                    <img src="<?= $sInfo['logo'] ?>" alt="Sponsor"
                        style="max-height: 90px; max-width: 280px; object-fit: contain;">
                <?php endif; ?>
            </div>

            <!-- Event Info -->
            <div class="hero-event-info" style="text-align: center; margin-bottom: 20px;">
                <?php if ($nextEvent): ?>
                    <h1 class="hero-event-title"><?= htmlspecialchars($nextEvent['nome']) ?></h1>
                    <p class="hero-event-meta" style="margin-bottom: 10px;">
                        <?= date('d/m/Y', strtotime($nextEvent['data'])) ?>
                        <?php if (!empty($nextEvent['hora'])): ?>
                            às <?= date('H:i', strtotime($nextEvent['hora'])) ?>
                        <?php endif; ?> &mdash;
                        <?= htmlspecialchars($nextEvent['kartodromo']) ?>
                        🗺️ 🏁
                    </p>

                    <!-- Calendar Integration -->
                    <?php
                    $eventTitle = urlencode("KartOps: " . $nextEvent['nome']);
                    $eventDate = date('Ymd\THis', strtotime($nextEvent['data'] . ' ' . $nextEvent['hora']));
                    $eventEndDate = date('Ymd\THis', strtotime($nextEvent['data'] . ' ' . $nextEvent['hora']) + 3600);
                    $eventLocation = urlencode($nextEvent['kartodromo']);
                    $googleCalUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text=$eventTitle&dates=$eventDate/$eventEndDate&location=$eventLocation";
                    ?>
                    <div class="calendar-links" style="display: flex; justify-content: center; gap: 15px; margin-top: 5px;">
                        <span style="font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; display: flex; align-items: center;">Adicionar ao calendário:</span>
                        <a href="<?= $googleCalUrl ?>" target="_blank" title="Google Calendar" style="display: flex; align-items: center; transition: transform 0.2s;">
                           <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Google_Calendar_icon_%282020%29.svg" style="width: 20px; height: 20px;" alt="Google">
                        </a>
                        <a href="etapa_ics.php?id=<?= $nextEvent['id'] ?>" title="Apple Calendar" style="display: flex; align-items: center; transition: transform 0.2s;">
                           <svg viewBox="0 0 384 512" style="width: 20px; height: 20px; fill: #ffffff;" xmlns="http://www.w3.org/2000/svg"><path d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/></svg>
                        </a>
                    </div>

                <?php else: ?>
                    <h1 class="hero-event-title">Próxima etapa em breve</h1>
                <?php endif; ?>
            </div>

            <!-- Blue Pill Button -->
            <a href="etapas.php" class="hero-btn-stages" style="margin-bottom: 24px;">
                VEJA TODAS AS ETAPAS DO CAMPEONATO
            </a>

            <!-- Countdown (Last Item) -->
            <div class="hero-countdown-row" style="flex-direction: column; gap: 16px;">
                <div class="hero-countdown" id="main-countdown" style="justify-content: center;">
                    <div class="cd-block">
                        <span class="cd-val" id="cd-days">--</span>
                        <span class="cd-unit">DIAS</span>
                    </div>
                    <div class="cd-block">
                        <span class="cd-val" id="cd-hours">--</span>
                        <span class="cd-unit">HORAS</span>
                    </div>
                    <div class="cd-block">
                        <span class="cd-val" id="cd-mins">--</span>
                        <span class="cd-unit">MINS</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: ONBOARDS -->
        <?php
        // Buscar os 4 últimos onboards do banco
        $latestOnboards = [];
        try {
            $stmtOnb = $pdo->query("
                SELECT o.id, o.titulo, o.youtube_video_id, p.nome as piloto_nome
                FROM onboards o
                LEFT JOIN pilotos p ON o.piloto_id = p.id
                ORDER BY RAND()
                LIMIT 8
            ");
            $latestOnboards = $stmtOnb->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* sem onboards */
        }
        ?>
        <section class="onboard-section-featured bento-card clickable-bento" onclick="window.location.href='onboards.php'">
            <!-- Header -->
            <div class="onb-header">
                <h2 class="onb-title">ONBOARDS</h2>
                <a href="onboards.php" class="onb-view-all">
                    Ver todos &nbsp;
                    <span class="onb-arrow">&#8249;</span>
                    <span class="onb-arrow">&#8250;</span>
                </a>
            </div>

            <!-- Video Grid: 4 desktop / 2 mobile -->
            <div class="onb-grid">
                <?php if (empty($latestOnboards)): ?>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="onb-card" style="position: relative; cursor: pointer;"
                            onclick="window.location.href='onboards.php'">
                            <!-- Link Overlay para interceptar clique Youtube (preserva autoplay visual original) -->
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;"></div>

                            <div class="onb-iframe-wrap">
                                <iframe
                                    src="https://www.youtube.com/embed/o2afFeNp8U8?autoplay=1&mute=1&controls=0&loop=1&playlist=o2afFeNp8U8&modestbranding=1&showinfo=0&rel=0"
                                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php else: ?>
                    <?php foreach ($latestOnboards as $onb): ?>
                        <div class="onb-card" style="position: relative; cursor: pointer;"
                            onclick="window.location.href='onboards.php'">
                            <!-- Link Overlay para interceptar clique Youtube (preserva autoplay visual original) -->
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;"></div>

                            <div class="onb-iframe-wrap">
                                <iframe
                                    src="https://www.youtube.com/embed/<?= htmlspecialchars($onb['youtube_video_id']) ?>?autoplay=1&mute=1&controls=0&loop=1&playlist=<?= htmlspecialchars($onb['youtube_video_id']) ?>&modestbranding=1&showinfo=0&rel=0"
                                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 3: CLICADOS -->
        <?php
        /**
         * Parseia fotos.txt no formato hierárquico:
         *   - Etapa
         *   -- Pasta
         *   --- URL da pasta do Drive
         */
        function parseFotosHierarquico(string $path): array
        {
            if (!file_exists($path))
                return [];
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $entries = [];
            $etapa = '';
            $pasta = '';
            $lastIdx = -1;
            foreach ($lines as $line) {
                $l = trim($line);
                if ($l === '' || $l[0] === '#')
                    continue;
                if (str_starts_with($l, '---- ')) {
                    // Data da pasta anterior
                    if ($lastIdx >= 0) {
                        $entries[$lastIdx]['data'] = trim(substr($l, 5));
                    }
                } elseif (str_starts_with($l, '--- ')) {
                    $url = trim(substr($l, 4));
                    if (preg_match('/drive\.google\.com\/drive\/folders\/([a-zA-Z0-9_-]+)/', $url, $m)) {
                        $entries[] = ['etapa' => $etapa, 'pasta' => $pasta, 'folderUrl' => $url, 'folderId' => $m[1], 'data' => ''];
                        $lastIdx = count($entries) - 1;
                    }
                } elseif (str_starts_with($l, '-- ')) {
                    $pasta = trim(substr($l, 3));
                } elseif (str_starts_with($l, '- ')) {
                    $etapa = trim(substr($l, 2));
                    $pasta = '';
                    $lastIdx = -1;
                }
            }
            return $entries;
        }

        function carregarTodasFotos(string $fotosPath, string $apiKey): array
        {
            $entries = parseFotosHierarquico($fotosPath);
            $todasFotos = [];
            foreach ($entries as $entry) {
                if ($apiKey === '' || empty($entry['folderId']))
                    continue;
                $apiUrl = 'https://www.googleapis.com/drive/v3/files'
                    . '?q=' . urlencode("'{$entry['folderId']}' in parents and mimeType contains 'image/' and trashed=false")
                    . '&fields=files(id)'
                    . '&pageSize=50'
                    . '&key=' . urlencode($apiKey);
                $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                $json = @file_get_contents($apiUrl, false, $ctx);
                if ($json) {
                    $data = json_decode($json, true);
                    foreach ($data['files'] ?? [] as $file) {
                        $todasFotos[] = [
                            'url' => '/photos_proxy.php?id=' . $file['id'],
                            'fileId' => $file['id'],
                            'etapa' => $entry['etapa'],
                            'pasta' => $entry['pasta'],
                            'folderUrl' => $entry['folderUrl'],
                        ];
                    }
                }
            }
            shuffle($todasFotos);
            return $todasFotos;
        }

        $apiKey = defined('GOOGLE_DRIVE_API_KEY') ? GOOGLE_DRIVE_API_KEY : '';
        $fotosPool = carregarTodasFotos(__DIR__ . '/fotos.txt', $apiKey);
        $fotosShow = array_slice($fotosPool, 0, 8);
        if (empty($fotosShow))
            $fotosShow = array_fill(0, 4, null);
        ?>
        <section class="clicados-section bento-card clickable-bento" style="margin-top: 30px;" onclick="window.location.href='photos.php'">
            <div class="clicados-header">
                <h2 class="section-title">Clicados</h2>
                <a href="photos.php" class="onb-view-all" style="font-size: 12px;">Ver todas</a>
            </div>

            <div class="clicados-grid" id="clicados-grid">
                <?php foreach ($fotosShow as $i => $foto): ?>
                    <a href="photos.php" class="clicados-card <?= $i >= 2 ? 'hide-mobile' : '' ?>"
                        id="clicados-card-<?= $i ?>">
                        <?php if ($foto): ?>
                            <img src="<?= htmlspecialchars($foto['url']) ?>"
                                alt="<?= htmlspecialchars($foto['etapa'] . ' - ' . $foto['pasta']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="clicados-placeholder">
                                <span>📷</span>
                                <p>Adicione pastas em<br><code>fotos.txt</code></p>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- JSON pool para slideshow JS -->
        <?php if (!empty($fotosPool)): ?>
            <script>
                (function () {
                    const pool = <?= json_encode(array_map(fn($f) => $f['url'], $fotosPool), JSON_UNESCAPED_SLASHES) ?>;
                    const cards = document.querySelectorAll('#clicados-grid .clicados-card');
                    let idx = <?= count($fotosShow) ?>;

                    function nextPhoto() {
                        cards.forEach(card => {
                            const img = card.querySelector('img');
                            if (!img || idx >= pool.length) { idx = 0; return; }
                            img.style.opacity = '0';
                            setTimeout(() => {
                                img.src = pool[idx % pool.length];
                                img.style.opacity = '1';
                                idx++;
                            }, 400);
                        });
                        if (idx >= pool.length) idx = 0;
                    }
                    setInterval(nextPhoto, 5000);
                })();
            </script>
        <?php endif; ?>
        </section>

    </div>

    <!-- COLUNA 2 -->
    <div class="grid-column">

        <!-- SECTION 4 & 5: PILOTS STANDINGS -->
        <section class="pilots-standings-section bento-card clickable-bento" onclick="window.location.href='classificacao.php'">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 class="section-title">Classificação</h2>
                <div class="category-toggle-switch" onclick="event.stopPropagation()">
                    <button class="toggle-btn active" data-cat="master" onclick="toggleCategory('master')">Master</button>
                    <button class="toggle-btn" data-cat="challenger" onclick="toggleCategory('challenger')">Challengers</button>
                </div>
            </div>

            <!-- Podium Top 3 -->
            <div class="pilots-rank-podium" id="cls-podium">
                <?php if (count($masterData) >= 3): ?>
                    <div class="podium-item second">
                        <div class="podium-avatar">
                            <img id="pod-2-img"
                                src="<?= htmlspecialchars($masterData[1]['foto'] ?: '/images/turtle-driver.png') ?>"
                                onerror="this.src='/images/turtle-driver.png'" alt="">
                        </div>
                        <span class="podium-name" id="pod-2-name"><?= htmlspecialchars($masterData[1]['nome']) ?></span>
                        <span class="podium-pts" id="pod-2-pts"><?= $masterData[1]['total'] ?> PTS</span>
                    </div>
                    <div class="podium-item first">
                        <div class="podium-avatar podium-avatar--first">
                            <img id="pod-1-img"
                                src="<?= htmlspecialchars($masterData[0]['foto'] ?: '/images/turtle-driver.png') ?>"
                                onerror="this.src='/images/turtle-driver.png'" alt="">
                        </div>
                        <span class="podium-name" id="pod-1-name"><?= htmlspecialchars($masterData[0]['nome']) ?></span>
                        <span class="podium-pts podium-pts--first" id="pod-1-pts"><?= $masterData[0]['total'] ?> PTS</span>
                    </div>
                    <div class="podium-item third">
                        <div class="podium-avatar">
                            <img id="pod-3-img"
                                src="<?= htmlspecialchars($masterData[2]['foto'] ?: '/images/turtle-driver.png') ?>"
                                onerror="this.src='/images/turtle-driver.png'" alt="">
                        </div>
                        <span class="podium-name" id="pod-3-name"><?= htmlspecialchars($masterData[2]['nome']) ?></span>
                        <span class="podium-pts" id="pod-3-pts"><?= $masterData[2]['total'] ?> PTS</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Full Standings Table -->
            <h3 class="section-label">Full Standings</h3>
            <table class="rank-table">
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th>Driver</th>
                        <th style="text-align:right;">Pts</th>
                    </tr>
                </thead>
                <tbody id="cls-tbody">
                    <?php foreach (array_slice($masterData, 0, 5) as $p): ?>
                        <tr>
                            <td class="row-pos"><?= $p['pos'] ?></td>
                            <td class="row-pilot">
                                <img src="<?= htmlspecialchars($p['foto'] ?: '/images/turtle-driver.png') ?>"
                                    onerror="this.src='/images/turtle-driver.png'" alt="" class="mini-avatar"
                                    style="object-fit: cover; object-position: top center;">
                                <?= htmlspecialchars($p['nome']) ?>
                            </td>
                            <td class="row-pts"><?= $p['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <a href="classificacao.php" class="btn-all-stages"
                style="display:block;text-align:center;margin-top:20px;text-decoration:none;padding:12px;background:#1565c0;color:#fff;font-weight:700;border-radius:8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">
                Ver resultado completo
            </a>
        </section>

        <script>
            (function () {
                const datasets = {
                    master: <?= json_encode(array_slice($masterData, 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    challenger: <?= json_encode(array_slice($challengerData, 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                };
                let current = 'master';

                window.toggleCategory = function (forceCat) {
                    if (forceCat) current = forceCat;
                    else current = current === 'master' ? 'challenger' : 'master';
                    
                    const data = datasets[current];
                    
                    // Update Toggle Buttons UI
                    document.querySelectorAll('.pilots-standings-section .toggle-btn').forEach(btn => {
                        btn.classList.toggle('active', btn.dataset.cat === current);
                    });

                    // Update podium
                    [1, 2, 3].forEach(pos => {
                        const p = data[pos - 1];
                        if (!p) return;
                        const imgEl = document.getElementById('pod-' + pos + '-img');
                        imgEl.src = p.foto || '/images/turtle-driver.png';
                        imgEl.onerror = () => { imgEl.src = '/images/turtle-driver.png'; };
                        document.getElementById('pod-' + pos + '-name').textContent = p.nome;
                        document.getElementById('pod-' + pos + '-pts').textContent = p.total + ' PTS';
                    });

                    // Rebuild table
                    document.getElementById('cls-tbody').innerHTML = data.slice(0, 5).map(p => `
                    <tr>
                        <td class="row-pos">${p.pos}</td>
                        <td class="row-pilot"><img src="${p.foto || '/images/turtle-driver.png'}" onerror="this.src='/images/turtle-driver.png'" alt="" class="mini-avatar" style="object-fit: cover; object-position: top center;"> ${p.nome}</td>
                        <td class="row-pts">${p.total}</td>
                    </tr>`).join('');
                };
            })();
        </script>

        <!-- SECTION 6 & 7: EQUIPES STANDINGS -->
        <section class="teams-standings-section bento-card clickable-bento" style="margin-top: 30px;" onclick="window.location.href='classificacao.php?tipo=equipes'">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 class="section-title">Classificação De Equipes</h2>
                <div class="category-toggle-switch" onclick="event.stopPropagation()">
                    <button class="toggle-btn active" data-cat="master" onclick="toggleTeamCategory('master')">Master</button>
                    <button class="toggle-btn" data-cat="challenger" onclick="toggleTeamCategory('challenger')">Challengers</button>
                </div>
            </div>

            <!-- Equipe líder (atualizada dinamicamente) -->
            <div id="team-leader-card"
                style="background: rgba(40,42,43,0.5); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <?php
                $leaderData = $topTeamMaster;
                $leaderPilots = $leaderData ? $leaderData['pilotos_master'] : [];
                ?>
                <?php if ($leaderData): ?>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div style="display: flex; flex-wrap: wrap;" id="team-leader-pilots-container">
                            <?php foreach ($leaderPilots as $pilot): ?>
                                <img src="<?= htmlspecialchars($pilot['foto'] ?: '/images/turtle-driver.png') ?>"
                                    onerror="this.src='/images/turtle-driver.png'" class="team-leader-pilot-avatar"
                                    style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid #1a1a1a; margin-left: -20px; object-fit: cover; object-position: top center; background: #222;">
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <div>
                                <p
                                    style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">
                                    Team</p>
                                <h4 id="team-leader-name"
                                    style="font-size: 16px; font-weight: 800; color: var(--accent-yellow);">
                                    <?= htmlspecialchars($leaderData['nome']) ?>
                                </h4>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="section-label">Classificação</h3>
            <table class="rank-table">
                <tbody id="team-cls-tbody">
                    <?php foreach ($teamResults as $k => $team): ?>
                        <tr>
                            <td class="row-pos"><?= $k + 1 ?></td>
                            <td class="row-pilot">
                                <img src="<?= htmlspecialchars($team['foto'] ?: '/images/turtle-driver.png') ?>"
                                    onerror="this.src='/images/turtle-driver.png'" alt=""
                                    style="max-width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px;">
                                <?= htmlspecialchars($team['nome']) ?>
                            </td>
                            <td class="row-pts"><?= $team['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <a href="classificacao.php" class="btn-all-stages"
                style="display:block;text-align:center;margin-top:20px;text-decoration:none;padding:12px;background:#8B3A5E;color:#fff;font-weight:700;border-radius:8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;">
                Ver resultado completo
            </a>
        </section>

        <script>
            (function () {
                const teamDatasets = {
                    master: <?= json_encode(array_slice($teamMasterData, 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    challenger: <?= json_encode(array_slice($teamChallengerData, 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                };
                let teamCurrent = 'master';

                window.toggleTeamCategory = function (forceCat) {
                    if (forceCat) teamCurrent = forceCat;
                    else teamCurrent = teamCurrent === 'master' ? 'challenger' : 'master';
                    
                    const data = teamDatasets[teamCurrent];
                    
                    // Update Toggle Buttons UI
                    document.querySelectorAll('.teams-standings-section .toggle-btn').forEach(btn => {
                        btn.classList.toggle('active', btn.dataset.cat === teamCurrent);
                    });

                    // Atualiza líder
                    const leader = data[0];
                    if (leader) {
                        const nameEl = document.getElementById('team-leader-name');
                        const pilotsContainer = document.getElementById('team-leader-pilots-container');
                        if (nameEl) nameEl.textContent = leader.nome;

                        // Reconstrói avatares empilhados dos pilotos membros da equipe
                        const membersArray = teamCurrent === 'master' ? leader.pilotos_master : leader.pilotos_challengers;
                        if (pilotsContainer && membersArray) {
                            pilotsContainer.innerHTML = membersArray.map(p => {
                                const photo = p.foto || '/images/turtle-driver.png';
                                return `<img src="${photo}" onerror="this.src='/images/turtle-driver.png'" class="team-leader-pilot-avatar" style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid #1a1a1a; margin-left: -20px; object-fit: cover; object-position: top center; background: #222;">`;
                            }).join('');
                        }
                    }

                    // Reconstrói tabela
                    document.getElementById('team-cls-tbody').innerHTML = data.map((t, k) => `
                        <tr>
                            <td class="row-pos">${k + 1}</td>
                            <td class="row-pilot"><img src="${t.foto || '/images/turtle-driver.png'}" onerror="this.src='/images/turtle-driver.png'" alt="" style="max-width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px;"> ${t.nome}</td>
                            <td class="row-pts">${t.total}</td>
                        </tr>`).join('');
                };
            })();
        </script>

    </div>

    <!-- COLUNA 3 -->
    <div class="grid-column">


        <!-- SECTION: CONHEÇA AS EQUIPES -->
        <?php if ($teamHighlight): ?>
        <section class="meet-teams-section bento-card clickable-bento" onclick="window.location.href='equipes.php'">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 20px;">
                <h2 class="bento-title" style="margin-bottom: 0;">Conheça as equipes</h2>
                <span class="view-all-link" style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; border: 1px solid var(--glass-border); padding: 4px 10px; border-radius: 4px;">Ver todas</span>
            </div>

            <div class="team-highlight-square">
                <?php if ($teamHighlight['foto']): ?>
                    <img src="<?= htmlspecialchars($teamHighlight['foto']) ?>" alt="<?= htmlspecialchars($teamHighlight['nome']) ?>" class="team-logo-hero">
                <?php else: ?>
                    <div class="team-logo-placeholder"><?= mb_substr($teamHighlight['nome'], 0, 1) ?></div>
                <?php endif; ?>
                
                <div class="team-hero-info">
                    <h3 class="team-hero-name"><?= htmlspecialchars($teamHighlight['nome']) ?></h3>
                    
                    <div class="team-stats-grid">
                        <div class="team-stat-item">
                            <span class="stat-label">CHEFE</span>
                            <span class="stat-value"><?= htmlspecialchars($teamHighlight['chefe']) ?></span>
                        </div>
                        <div class="team-stat-item">
                            <span class="stat-label">TOP MASTER</span>
                            <span class="stat-value"><?= htmlspecialchars($teamHighlight['top_master']) ?></span>
                        </div>
                        <div class="team-stat-item">
                            <span class="stat-label">TOP CHALLENGERS</span>
                            <span class="stat-value"><?= htmlspecialchars($teamHighlight['top_challenger']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- SECTION 9: PILOTOS -->
        <section class="meet-pilots-section bento-card clickable-bento" style="margin-top: 30px;" onclick="window.location.href='pilotos.php'">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 20px;">
                <div>
                    <h2 class="bento-title" style="margin-bottom: 0;">Conheça nossos pilotos</h2>
                </div>
                <a href="pilotos.php" class="view-all-link"
                    style="font-size: 10px; color: var(--text-secondary); text-transform: uppercase; text-decoration: none; border: 1px solid var(--glass-border); padding: 4px 10px; border-radius: 4px;">Mais</a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;" class="pilots-grid">
                <?php foreach (array_slice($featuredPilots, 0, 8) as $idx => $p): ?>
                    <div class="pilot-thumb-card <?= $idx >= 4 ? 'hide-mobile' : '' ?>"
                        style="background: linear-gradient(180deg, transparent, rgba(0,0,0,0.8)); border-radius: 12px; height: 184px; position: relative; overflow: hidden; border: 1px solid var(--glass-border); cursor: pointer;"
                        onclick="window.location.href='pilotos.php'">
                        <img src="<?= htmlspecialchars($p['foto'] ?: '/images/turtle-driver.png') ?>"
                            onerror="this.src='/images/turtle-driver.png'"
                            style="width: 100%; height: 100%; object-fit: cover; object-position: top center;"
                            alt="<?= htmlspecialchars($p['nome']) ?>">
                        <div style="position: absolute; bottom: 10px; left: 10px;">
                            <span
                                style="font-size: 11px; font-weight: 800; color: var(--accent-yellow);"><?= htmlspecialchars($p['apelido'] ?: $p['nome']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>


        <!-- SECTION: RESULTADOS (FINAL SECTION) -->
        <section class="results-banner-section bento-card" onclick="window.location.href='resultados.php'" style="grid-column: 1 / -1; cursor: pointer; padding: 0; overflow: hidden; background: #1c1e22; display: flex; align-items: center; min-height: 200px;">
            <div class="results-banner-content" style="flex: 1; padding: 40px; z-index: 2;">
                <h2 class="bento-title" style="margin-bottom: 10px; color: #fff; font-size: 32px;">RESULTADOS</h2>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 20px; font-weight: 500;">Confira os resultados das etapas já ocorridas.</p>
                <span class="hero-btn-stages" style="display: inline-block; width: auto; padding: 12px 30px;">ACESSAR PAINEL DE RESULTADOS</span>
            </div>
            <div class="results-banner-art" style="flex: 1; height: 100%; position: relative; display: flex; justify-content: flex-end; align-items: center;">
                <img src="/images/nano_banana.png" alt="Nano Banana" style="height: 280px; width: auto; object-fit: contain; margin-right: -20px; filter: drop-shadow(-10px 10px 20px rgba(0,0,0,0.5));">
            </div>
        </section>

    </div>

</div>

<?php if (isset($sponsorModalData)):
    $sInfo = getSponsorInfo($sponsorModalData['nome_patrocinador']);
    ?>
    <!-- Modal do Patrocinador (Sponsor of the Month) -->
    <div id="sponsor-modal-overlay"
        style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.85); z-index: 999999; display: flex; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; backdrop-filter: blur(5px);">
        <div class="sponsor-modal-content"
            style="background: #1a2535; padding: 40px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center; max-width: 500px; width: 90%; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); transform: translateY(30px) scale(0.95); transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <button onclick="closeSponsorModal()"
                style="position: absolute; top: 15px; right: 15px; background: transparent; border: none; color: #9ca3af; font-size: 28px; cursor: pointer; line-height: 1; padding: 5px; transition: color 0.2s;">&times;</button>

            <h3
                style="color: #6366f1; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 25px; font-weight: 700;">
                <?= htmlspecialchars($sponsorModalData['titulo_modal']) ?>
            </h3>

            <div
                style="background: #ffffff; padding: 20px; border-radius: 12px; display: inline-flex; justify-content: center; align-items: center; margin-bottom: 25px;">
                <img src="<?= $sInfo['logo'] ?>" alt="<?= htmlspecialchars($sponsorModalData['nome_patrocinador']) ?>"
                    style="max-height: 100px; max-width: 100%; object-fit: contain;">
            </div>

            <h2 style="color: #ffffff; font-size: 24px; margin-bottom: 12px; font-weight: 700;">
                <?= htmlspecialchars($sponsorModalData['nome_patrocinador']) ?>
            </h2>
            <p style="color: #9ca3af; font-size: 15px; line-height: 1.6; margin-bottom: 30px;">
                <?= htmlspecialchars($sponsorModalData['texto_modal']) ?>
            </p>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <?php if (!empty($sInfo['site']) && $sInfo['site'] !== '#'): ?>
                    <a href="<?= $sInfo['site'] ?>" target="_blank" onclick="closeSponsorModal()"
                        style="background: #6366f1; color: #ffffff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; transition: background 0.2s; flex: 1;">Visitar
                        Site</a>
                <?php elseif (!empty($sInfo['whatsapp'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sInfo['whatsapp']) ?>?text=Ol%C3%A1%21%20Vim%20pelo%20App%20KartOps."
                        target="_blank" onclick="closeSponsorModal()"
                        style="background: #25D366; color: #ffffff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; transition: background 0.2s; flex: 1; display:flex; justify-content:center; align-items:center; gap:8px;">
                        <i class="fab fa-whatsapp"></i> Falar no WhatsApp
                    </a>
                <?php endif; ?>
                <button onclick="closeSponsorModal()"
                    style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,255,255,0.1); color: #ffffff; padding: 12px 28px; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; transition: background 0.2s; flex: 1;">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const overlay = document.getElementById('sponsor-modal-overlay');
            const content = overlay.querySelector('.sponsor-modal-content');

            // Show modal smoothly with a slight delay
            setTimeout(() => {
                overlay.style.opacity = '1';
                content.style.transform = 'translateY(0) scale(1)';
            }, 300);
        });

        function closeSponsorModal() {
            const overlay = document.getElementById('sponsor-modal-overlay');
            const content = overlay.querySelector('.sponsor-modal-content');

            overlay.style.opacity = '0';
            content.style.transform = 'translateY(20px) scale(0.95)';

            setTimeout(() => {
                overlay.style.display = 'none';

                const isGuest = <?= (isset($_SESSION['is_guest']) && $_SESSION['is_guest']) ? 'true' : 'false' ?>;
                const date = new Date();
                const hours = isGuest ? 1 : 24;
                date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
                document.cookie = "sponsor_seen=1; expires=" + date.toUTCString() + "; path=/";

                // Dispara fallback do toast para visitantes (se aplicável), evitando sobreposição na tela.
                window.pendingSponsorModal = false;
                if (typeof window.showVisitorToast === 'function') {
                    window.showVisitorToast();
                }
            }, 400);
        }
    </script>
<?php endif; ?>

<!-- Countdown Script -->
<script>
    function updateCountdown() {
        const nextEventDate = new Date("<?= $nextEvent ? $nextEvent['data'] . ' ' . $nextEvent['hora'] : '' ?>").getTime();
        const now = new Date().getTime();
        const gap = nextEventDate - now;

        if (gap > 0) {
            const d = Math.floor(gap / (1000 * 60 * 60 * 24));
            const h = Math.floor((gap % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((gap % (1000 * 60 * 60)) / (1000 * 60));

            document.getElementById('cd-days').innerText = d.toString().padStart(2, '0');
            document.getElementById('cd-hours').innerText = h.toString().padStart(2, '0');
            document.getElementById('cd-mins').innerText = m.toString().padStart(2, '0');
        }
    }
    setInterval(updateCountdown, 1000);
    updateCountdown();
</script>

<?php require_once 'includes/footer.php'; ?>