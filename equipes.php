<?php
$pageTitle = 'Equipes';
$additionalCSS = ['/css/equipes.css'];
require_once 'includes/header.php';

// Auth Check
if (!$usuario) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();
$msg = '';
$msgType = '';

// =====================================================
// ACTION HANDLER
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner') {

        // EDITAR EQUIPE
        if ($_POST['action'] === 'edit') {
            try {
                $id = $_POST['id'];
                $nome = trim($_POST['nome']);
                $cor = trim($_POST['cor']);
                $chefe = !empty($_POST['chefe']) ? $_POST['chefe'] : null;
                $logoPath = trim($_POST['logo']); // Manual input
                $fotoSocialPath = trim($_POST['foto_social']); // Manual input

                // UPDATE (Sem automação de slug se vazio, usuário controla)
                $stmt = $pdo->prepare("UPDATE equipes SET nome = ?, cor = ?, imagem = ?, chefe = ?, foto_social = ? WHERE id = ?");
                $stmt->execute([$nome, $cor, $logoPath, $chefe, $fotoSocialPath, $id]);

                $msg = "Equipe atualizada com sucesso!";
                $msgType = "success";
            } catch (PDOException $e) {
                error_log("[KartOps] Erro: " . $e->getMessage());
$msg = 'Erro interno do servidor. Tente novamente mais tarde.';
                $msgType = "error";
            }
        }

        // CRIAR EQUIPE (Admin ou Owner apenas)
        elseif ($_POST['action'] === 'create') {
            if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner') {
                try {
                    $nome = trim($_POST['nome']);
                    $cor = trim($_POST['cor']);
                    $chefe = !empty($_POST['chefe']) ? $_POST['chefe'] : null;
                    $logoPath = trim($_POST['logo']);
                    $fotoSocialPath = trim($_POST['foto_social']);

                    // INSERT (Generating UUID via MySQL)
                    $stmt = $pdo->prepare("INSERT INTO equipes (id, nome, cor, imagem, chefe, foto_social, pontuacao) VALUES (UUID(), ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$nome, $cor, $logoPath, $chefe, $fotoSocialPath]);

                    $msg = "Equipe criada com sucesso!";
                    $msgType = "success";
                } catch (PDOException $e) {
                    error_log("[KartOps] Erro: " . $e->getMessage());
$msg = 'Erro interno do servidor. Tente novamente mais tarde.';
                    $msgType = "error";
                }
            } else {
                $msg = "Permissão negada.";
                $msgType = "error";
            }
        }

        // ADICIONAR PILOTO
        elseif ($_POST['action'] === 'add_pilot') {
            try {
                $equipeId = $_POST['equipe_id'];
                $pilotoId = $_POST['piloto_id'];

                if ($pilotoId) {
                    $stmt = $pdo->prepare("UPDATE pilotos SET equipe_id = ? WHERE id = ?");
                    $stmt->execute([$equipeId, $pilotoId]);
                    $msg = "Piloto adicionado à equipe!";
                    $msgType = "success";
                }
            } catch (PDOException $e) {
                error_log("[KartOps] Erro: " . $e->getMessage());
$msg = 'Erro interno do servidor. Tente novamente mais tarde.';
                $msgType = "error";
            }
        }

        // REMOVER PILOTO
        elseif ($_POST['action'] === 'remove_pilot') {
            try {
                $pilotoId = $_POST['piloto_id'];

                if ($pilotoId) {
                    $stmt = $pdo->prepare("UPDATE pilotos SET equipe_id = NULL WHERE id = ?");
                    $stmt->execute([$pilotoId]);
                    $msg = "Piloto removido da equipe!";
                    $msgType = "success";
                }
            } catch (PDOException $e) {
                error_log("[KartOps] Erro: " . $e->getMessage());
$msg = 'Erro interno do servidor. Tente novamente mais tarde.';
                $msgType = "error";
            }
        }
    }
}


// =====================================================
// DATA FETCHING
// =====================================================
$equipesData = [];
$msg = $msg ?? ''; // Ensure msg variable exists

try {
    // 1. Buscar Equipes
    $stmt = $pdo->query("
        SELECT 
            e.*, 
            p.nome as chefe_nome_db
        FROM equipes e 
        LEFT JOIN pilotos p ON e.chefe = p.id 
    ");
    $equipesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Buscar TODOS os pilotos vinculados com estatísticas de pódio
    $pStmt = $pdo->query("
        SELECT 
            p.id, p.nome, p.apelido, p.foto, p.equipe_id,
            c.nome as categoria_nome,
            (SELECT COUNT(*) FROM resultados r WHERE r.piloto_id = p.id AND r.posicao = 1 AND r.desclassificado = 0) as v1,
            (SELECT COUNT(*) FROM resultados r WHERE r.piloto_id = p.id AND r.posicao = 2 AND r.desclassificado = 0) as v2,
            (SELECT COUNT(*) FROM resultados r WHERE r.piloto_id = p.id AND r.posicao = 3 AND r.desclassificado = 0) as v3,
            (SELECT COUNT(*) FROM resultados r WHERE r.piloto_id = p.id AND r.posicao IN (4,5,6) AND r.desclassificado = 0) as p456
        FROM pilotos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.equipe_id IS NOT NULL
    ");
    $todosPilotosVinculados = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2.1 Obter Ranks e Pontos Totais via Helper para todos os pilotos
    require_once 'includes/classificacao_helper.php';
    $masterRankData = getClassificationData($pdo, 'Master');
    $challengerRankData = getClassificationData($pdo, 'Challenger');

    $pilotStatsLookup = [];
    foreach ($masterRankData as $pr) {
        $pilotStatsLookup[$pr['id']] = ['pos' => $pr['pos'], 'total' => $pr['total']];
    }
    foreach ($challengerRankData as $pr) {
        $pilotStatsLookup[$pr['id']] = ['pos' => $pr['pos'], 'total' => $pr['total']];
    }

    // 3. Buscar TODOS os Resultados para cálculo de pontos E vitórias da equipe
    $rStmt = $pdo->query("
        SELECT r.equipe_id, r.etapa_id, r.categoria, r.pontos, r.posicao,
               COALESCE(r.pontos_penalidade, 0) as pontos_penalidade,
               COALESCE(r.desclassificado, 0) as desclassificado
        FROM resultados r
        WHERE r.equipe_id IS NOT NULL
    ");
    $allResults = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    // -- PROCESSAMENTO --
    $teamPoints = [];
    $teamVictoriesMap = [];
    $groupedResults = [];

    foreach ($allResults as $row) {
        $eid = $row['equipe_id'];
        $eidNorm = trim(strtolower($eid));

        // Init Map if needed
        if (!isset($teamVictoriesMap[$eidNorm])) {
            $teamVictoriesMap[$eidNorm] = ['master' => 0, 'challengers' => 0];
        }

        // --- Victory Logic ---
        // Victory if pos=1 and NOT disqualified
        if ($row['posicao'] == 1 && $row['desclassificado'] == 0) {
            if ($row['categoria'] === 'Master') {
                $teamVictoriesMap[$eidNorm]['master']++;
            } else {
                // Challengers (contains 'Challenge') or fallback
                $teamVictoriesMap[$eidNorm]['challengers']++;
            }
        }

        // --- Points Logic ---
        $bruto = (float) $row['pontos'];
        $penal = (float) $row['pontos_penalidade'];
        $liq = $row['desclassificado'] ? 0 : max(0, $bruto + $penal);

        // Grouping for top 2 rule
        $catGroup = ($row['categoria'] === 'Master') ? 'Master' : 'Challengers';
        $groupedResults[$eid][$row['etapa_id']][$catGroup][] = $liq;
    }

    // Calcular Totais de Pontos (Regra Top 2)
    foreach ($groupedResults as $eid => $etapas) {
        if (!isset($teamPoints[$eid])) {
            $teamPoints[$eid] = ['total' => 0, 'master' => 0, 'challengers' => 0];
        }
        foreach ($etapas as $etapaId => $groups) {
            $somaMaster = 0;
            if (isset($groups['Master'])) {
                rsort($groups['Master']);
                $somaMaster = array_sum(array_slice($groups['Master'], 0, 2));
            }
            $somaChal = 0;
            if (isset($groups['Challengers'])) {
                rsort($groups['Challengers']);
                $somaChal = array_sum(array_slice($groups['Challengers'], 0, 2));
            }
            $teamPoints[$eid]['master'] += $somaMaster;
            $teamPoints[$eid]['challengers'] += $somaChal;
            $teamPoints[$eid]['total'] += ($somaMaster + $somaChal);
        }
    }

    // Organizar pilotos por equipe_id
    $cacheBuster = time();
    $mapPilotos = [];
    foreach ($todosPilotosVinculados as $p) {
        if (!isset($mapPilotos[$p['equipe_id']])) {
            $mapPilotos[$p['equipe_id']] = [];
        }
        if (empty($p['foto'])) {
            $p['foto'] = '/images/turtle-driver.png';
        } else {
            if (strpos($p['foto'], '/images/fotos/pilotos/') !== false) {
                $p['foto'] = preg_replace('/\?.*$/', '', $p['foto']);
                $p['foto'] = $p['foto'] . '?v=' . $cacheBuster;
            }
        }
        
        // Injetar estatísticas de rank e pontos
        $stats = $pilotStatsLookup[$p['id']] ?? ['pos' => '-', 'total' => 0];
        $p['rank'] = $stats['pos'];
        $p['pontos_totais'] = $stats['total'];

        $mapPilotos[$p['equipe_id']][] = $p;
    }

    // Montar Array Final
    foreach ($equipesRaw as $eq) {
        $eqId = $eq['id'];
        $eqIdNorm = trim(strtolower($eqId));

        $eq['pilotos'] = $mapPilotos[$eqId] ?? [];
        $eq['chefe_nome'] = $eq['chefe_nome_db'];

        $pts = $teamPoints[$eqId] ?? ['total' => 0, 'master' => 0, 'challengers' => 0];
        $eq['pontuacao'] = $pts['total'];
        $eq['pontuacao_master'] = $pts['master'];
        $eq['pontuacao_challengers'] = $pts['challengers'];

        // Robust Access for Map
        $vitoriasArr = $teamVictoriesMap[$eqIdNorm] ?? ['master' => 0, 'challengers' => 0];
        $eq['vitorias_master'] = $vitoriasArr['master'] ?? 0;
        $eq['vitorias_challengers'] = $vitoriasArr['challengers'] ?? 0;

        if (empty($eq['cor']))
            $eq['cor'] = '#141E30';

        $equipesData[] = $eq;
    }

    // Ordenação
    usort($equipesData, function ($a, $b) {
        return $a['pontuacao'] <=> $b['pontuacao'];
    });

    if (empty($equipesData) && !empty($equipesRaw)) {
        // Fallback for visual debug
        // echo "<!-- Processing Error: equipesData is empty but Raw is not. -->";
        // Do not echo, just fill
        foreach ($equipesRaw as $eq)
            $equipesData[] = $eq;
    }

} catch (PDOException $e) {
    // Show error
    error_log("[KartOps] Erro DB em equipes: " . $e->getMessage());
    echo "<div style='background:red;color:white;padding:10px;'>Erro interno de banco de dados. Tente novamente mais tarde.</div>";
}


// Buscar Todos os Pilotos (Admin/Colab)
$allPilots = [];
if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Colaborador' || $usuario['role'] === 'Owner') {
    try {
        $stmt = $pdo->query("SELECT id, nome FROM pilotos ORDER BY nome");
        $allPilots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

// Validate Data Before Encode
// Turn off display errors briefly to preventWarning injection into JSON
$prevDisplayErrors = ini_get('display_errors');
ini_set('display_errors', 0);

$jsonTeams = json_encode($equipesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$jsonError = json_last_error_msg();

ini_set('display_errors', $prevDisplayErrors);

// Fallback if encode failed
if ($jsonTeams === false) {
    $jsonTeams = '[]';
    error_log("JSON Encode Error in equipes.php: " . $jsonError);
}
?>

<div class="equipes-wrapper">

    <!-- Botão Voltar para Home -->
    <a href="home.php" class="btn-back-equipes" title="Voltar para Home">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
        </svg>
    </a>

    <!-- Top Actions -->
    <div class="top-actions">
        <?php if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner'): ?>
            <button class="btn-add-team" onclick="openCreateModal()">
                +
            </button>
        <?php endif; ?>
    </div>

    <!-- Msg -->
    <?php if ($msg): ?>
        <div id="status-msg"
            style="position: absolute; top: 100px; left: 50%; transform: translateX(-50%); z-index: 1000; background: <?= $msgType == 'success' ? '#4caf50' : '#f44336' ?>; color: white; padding: 10px 20px; border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); transition: opacity 0.5s;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <script>
            setTimeout(() => {
                const msg = document.getElementById('status-msg');
                if (msg) {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- Nav -->
    <button class="nav-btn prev-btn" onclick="prevSlide()">❮</button>
    <button class="nav-btn next-btn" onclick="nextSlide()">❯</button>

    <!-- Card Container -->
    <div id="team-container" class="team-card-wrapper"
        style="width: 100%; max-width: 800px; display: flex; justify-content: center;"></div>

</div>

<!-- Modal Edit -->
<div id="editModal" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
    <div class="modal-content"
        style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
        <h2>Editar Equipe</h2>
        <button onclick="closeEditModal()"
            style="position: absolute; top: 15px; right: 15px; border: none; background: none; font-size: 1.5rem; cursor: pointer;">×</button>

        <form method="POST" style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
            <?= generateCsrfField() ?>
            <input type="hidden" name="action" id="form-action" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <h3 style="font-size: 1rem; color: #666; margin-bottom: 15px;">Dados Básicos</h3>

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 5px;">Nome</label>
                    <input type="text" name="nome" id="edit-nome" class="form-control" required
                        style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 5px;">Cor</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="color" id="edit-cor-picker"
                            onchange="document.getElementById('edit-cor').value = this.value"
                            style="width: 40px; height: 38px; border: none; cursor: pointer;">
                        <input type="text" name="cor" id="edit-cor" class="form-control" required
                            style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Foto da Equipe (Caminho)</label>
                <input type="text" name="logo" id="edit-logo" class="form-control"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;"
                    placeholder="/images/logos/equipes/..." oninput="updateImagePreview(this, 'edit-logo-preview')">
                <div
                    style="margin-top: 10px; text-align: center; background: #f0f0f0; padding: 10px; border-radius: 8px;">
                    <img id="edit-logo-preview" src="" alt="Preview"
                        style="max-height: 100px; max-width: 100%; object-fit: contain;"
                        onerror="handleImageError(this)">
                    <p style="font-size: 0.8rem; color: #999; margin-top: 5px;">Preview da Imagem</p>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Foto Social (Membros)</label>
                <input type="text" name="foto_social" id="edit-foto-social" class="form-control"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;"
                    placeholder="/images/logos/equipes/social_redburros.jpg..."
                    oninput="updateImagePreview(this, 'edit-foto-social-preview')">
                <div
                    style="margin-top: 10px; text-align: center; background: #f0f0f0; padding: 10px; border-radius: 8px;">
                    <img id="edit-foto-social-preview" src="" alt="Preview Social"
                        style="max-height: 150px; max-width: 100%; object-fit: cover;" onerror="handleImageError(this)">
                    <p style="font-size: 0.8rem; color: #999; margin-top: 5px;">Preview da Foto Social</p>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Chefe de Equipe</label>
                <select name="chefe" id="edit-chefe" class="form-control"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    <option value="">-- Não Definido --</option>
                    <?php foreach ($allPilots as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="text-align: right;">
                <button type="submit"
                    style="padding: 8px 16px; border: none; background: #2196f3; color: white; border-radius: 5px; cursor: pointer; font-weight: bold;">Salvar
                    Dados</button>
            </div>
        </form>

        <div>
            <h3 style="font-size: 1rem; color: #666; margin-bottom: 15px;">Membros da Equipe</h3>
            <ul id="members-list" style="list-style: none; padding: 0; margin-bottom: 15px;"></ul>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                    <?= generateCsrfField() ?>
                    <input type="hidden" name="action" value="add_pilot">
                    <input type="hidden" name="equipe_id" id="add-pilot-equipe-id">

                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.8rem; margin-bottom: 5px;">Adicionar Piloto</label>
                        <select name="piloto_id" class="form-control"
                            style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px;" required>
                            <option value="">-- Selecione um piloto --</option>
                            <?php foreach ($allPilots as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit"
                        style="padding: 8px 16px; background: #4caf50; color: white; border: none; border-radius: 5px; cursor: pointer;">+</button>
                </form>
            </div>
        </div>
    </div>
</div>

<form id="removePilotForm" method="POST" style="display: none;">
    <?= generateCsrfField() ?>
    <input type="hidden" name="action" value="remove_pilot">
    <input type="hidden" name="piloto_id" id="remove-piloto-id">
</form>

<script>
    window.onerror = function (message, source, lineno, colno, error) {
        const container = document.getElementById('team-container');
        if (container) {
            container.innerHTML = `<div style="color:white; background:#d32f2f; padding:20px; text-align:center; border-radius:8px; margin-top:20px;">
            <h3>Erro de Script (Javascript)</h3>
            <p>${message}</p>
            <small>Linha: ${lineno}</small>
        </div>`;
        }
        console.error("Global Error Caught:", message, "at line", lineno);
        return false;
    };

    function handleImageError(img) {
        if (!img) return;
        img.onerror = null;
        img.src = '/images/logo-campeonato.png';
    }

    function updateImagePreview(input, imgId) {
        const img = document.getElementById(imgId);
        if (!img) return;
        const val = input.value.trim();
        img.src = val || '/images/logo-campeonato.png';
    }
</script>

<script>
    // Server-side Data Injection
    const teams = <?= $jsonTeams ?>;
    const canEdit = <?= ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner') ? 'true' : 'false' ?>;
    const jsonError = "<?= $jsonTeams === '[]' && !empty($equipesData) ? 'Erro na geração do JSON PHP: ' . addslashes($jsonError) : '' ?>";

    // Debug
    console.log("Teams Data:", teams);
    if (jsonError) console.error(jsonError);

    let currentIndex = 0;
    const container = document.getElementById('team-container');
    const modal = document.getElementById('editModal');

    // Display JSON Error if any
    if (jsonError) {
        if (container) container.innerHTML = `<div class="no-teams" style="color:red">${jsonError}</div>`;
    }

    function renderTeam(index) {
        try {
            if (!teams || !Array.isArray(teams) || teams.length === 0) {
                if (container) container.innerHTML = '<div class="no-teams">Nenhuma equipe encontrada.</div>';
                return;
            }

            // Safe Index wrap
            if (index < 0) index = teams.length - 1;
            if (index >= teams.length) index = 0;
            currentIndex = index;

            const team = teams[index];
            if (!team) return;

            // Background Color with Fallback (Igual Pilotos)
            const color = team.cor || '#141E30';
            document.body.style.background = `radial-gradient(circle at center, ${color}cc 0%, #000 100%)`;

            // Pilots HTML Construction (Modernizado)
            let pilotsHtml = '';
            if (team.pilotos && Array.isArray(team.pilotos) && team.pilotos.length > 0) {
                pilotsHtml = '<div class="pilots-grid">';
                team.pilotos.forEach(p => {
                    const displayName = p.apelido ? p.apelido : p.nome;
                    const rank = p.rank || '-';
                    const pts = parseFloat(p.pontos_totais || 0).toFixed(1);
                    const cat = p.categoria_nome || '';
                    const catLabel = cat.toLowerCase().includes('master') ? 'Master' : 'Challengers';

                    // Medalhas e Pódios
                    const v1 = parseInt(p.v1) || 0;
                    const v2 = parseInt(p.v2) || 0;
                    const v3 = parseInt(p.v3) || 0;
                    const p456 = parseInt(p.p456) || 0;

                    let medalsHtml = '<div class="pilot-medals">';
                    if (v1 > 0) medalsHtml += `<span class="medal-item gold" title="Vitórias"><i class="fas fa-trophy"></i>${v1}</span>`;
                    if (v2 > 0) medalsHtml += `<span class="medal-item silver" title="2º Lugares"><i class="fas fa-trophy"></i>${v2}</span>`;
                    if (v3 > 0) medalsHtml += `<span class="medal-item bronze" title="3º Lugares"><i class="fas fa-trophy"></i>${v3}</span>`;
                    if (p456 > 0) medalsHtml += `<span class="medal-item podium" title="Pódios (4º-6º)"><img src="/images/icons/podio.png" class="podio-icon-mini">${p456}</span>`;
                    medalsHtml += '</div>';

                    pilotsHtml += `
                        <div class="pilot-mini-card">
                            <div class="pilot-info-left">
                                <img src="${p.foto}" 
                                     class="pilot-avatar" 
                                     alt="${displayName}"
                                     style="object-position: center top;"
                                     onerror="this.src='/images/turtle-driver.png'">
                                <div class="pilot-name-wrap">
                                    <div class="pilot-badge-name">${displayName}</div>
                                    <div class="pilot-rank-pts">
                                        <span class="p-cat">${catLabel}</span> • <span class="p-rank">#${rank}</span> • <span class="p-pts">${pts} pts</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pilot-stats-right">
                                ${medalsHtml}
                            </div>
                        </div>
                    `;
                });
                pilotsHtml += '</div>';
            } else {
                pilotsHtml = '<p style="color: rgba(255,255,255,0.3); font-style: italic; text-align: center; margin: 20px 0;">Nenhum piloto nesta equipe.</p>';
            }

            // Stats Section (Modernizado)
            const vitsMaster = team.vitorias_master || 0;
            const vitsChal = team.vitorias_challengers || 0;
            const ptsMaster = parseFloat(team.pontuacao_master || 0).toFixed(1);
            const ptsChal = parseFloat(team.pontuacao_challengers || 0).toFixed(1);

            const statsHtml = `
                <div class="team-stats-grid">
                    <div class="stat-box">
                        <span class="stat-label">Master 🏆</span>
                        <span class="stat-value">${vitsMaster} | ${ptsMaster} pts</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">Challengers 🏆</span>
                        <span class="stat-value">${vitsChal} | ${ptsChal} pts</span>
                    </div>
                </div>
            `;

            // Edit Button
            let editBtn = '';
            if (canEdit) {
                editBtn = `<button class="btn-edit-team" onclick="openEditModal(${index})" title="Editar Equipe">✏️</button>`;
            }

            const imgUrl = team.imagem || '/images/logo-campeonato.png';

            let socialPhotoHtml = '';
            if (team.foto_social) {
                socialPhotoHtml = `
                    <div class="team-social-photo" style="margin: 20px 0; position: relative;">
                        <img src="${team.foto_social}" 
                             style="width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); max-height: 400px; object-fit: cover; border: 1px solid rgba(255,255,255,0.05);"
                             alt="Foto da Equipe"
                             onerror="this.style.display='none'">
                    </div>
                `;
            }

            // Template Principal
            const html = `
                <div class="team-card">
                    ${editBtn}
                    <div style="padding-bottom: 20px;">
                        <img src="${imgUrl}" 
                             onerror="this.onerror=null; this.src='/images/logo-campeonato.png'" 
                             class="team-logo" alt="Logo da Equipe">
                    </div>
                    
                    <h2 class="team-name">${team.nome}</h2>
                    
                    ${statsHtml}

                    <div style="background: rgba(255,255,255,0.03); border-radius: 20px; padding: 15px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                        <span class="stat-label" style="margin-bottom: 4px;">Chefe de Equipe</span>
                        <span style="color: #fff; font-weight: 700; font-size: 1.1rem;">${team.chefe_nome ? team.chefe_nome : 'Não definido'}</span>
                    </div>

                    ${socialPhotoHtml}

                    <div class="team-pilots-section">
                        <h3>🏎️ Membros da Equipe</h3>
                        ${pilotsHtml}
                    </div>
                </div>
            `;

            container.innerHTML = html;

            // Trigger Animation
            const card = container.querySelector('.team-card');
            if (card) {
                card.style.animation = 'fadeIn 0.6s cubic-bezier(0.23, 1, 0.32, 1)';
            }

        } catch (e) {
            console.error("Render Error:", e);
            if (container) container.innerHTML = `<div style="color:red; background:rgba(0,0,0,0.5); padding:20px; border-radius:10px;">Erro ao renderizar: ${e.message}</div>`;
        }
    }

    function nextSlide() {
        if (!teams || teams.length === 0) return;
        currentIndex++;
        renderTeam(currentIndex);
    }
    function prevSlide() {
        if (!teams || teams.length === 0) return;
        currentIndex--;
        renderTeam(currentIndex);
    }

    // Modal & Form Functions
    function openEditModal(index) {
        try {
            if (!teams[index]) return;
            const team = teams[index];

            document.getElementById('form-action').value = 'edit';
            document.querySelector('#editModal h2').innerText = 'Editar Equipe';

            const membersSection = document.querySelector('#editModal > div > div:last-child');
            if (membersSection) membersSection.style.display = 'block';

            document.getElementById('edit-id').value = team.id;
            document.getElementById('edit-nome').value = team.nome || '';
            document.getElementById('edit-cor').value = team.cor || '#141E30';
            document.getElementById('edit-cor-picker').value = team.cor || '#141E30';

            setInputAndPreview('edit-logo', 'edit-logo-preview', team.imagem || '');
            setInputAndPreview('edit-foto-social', 'edit-foto-social-preview', team.foto_social || '');

            document.getElementById('edit-chefe').value = team.chefe || '';
            document.getElementById('add-pilot-equipe-id').value = team.id;

            // Populate Members
            const membersList = document.getElementById('members-list');
            membersList.innerHTML = '';

            if (team.pilotos && team.pilotos.length > 0) {
                team.pilotos.forEach(p => {
                    const li = document.createElement('li');
                    li.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff; border: 1px solid #eee; margin-bottom: 5px; border-radius: 5px;';
                    li.innerHTML = `
                        <span style="color: #333; font-weight: 500;">${p.nome}</span>
                        <button type="button" onclick="removePilot('${p.id}')" style="background: #ffcdd2; color: #c62828; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Remover</button>
                    `;
                    membersList.appendChild(li);
                });
            } else {
                membersList.innerHTML = '<li style="color: #999;">Sem pilotos nesta equipe.</li>';
            }

            modal.style.display = 'flex';
        } catch (e) {
            console.error(e);
            alert("Erro ao abrir modal: " + e.message);
        }
    }

    function openCreateModal() {
        document.getElementById('form-action').value = 'create';
        document.getElementById('edit-id').value = '';

        document.getElementById('edit-nome').value = '';
        document.getElementById('edit-cor').value = '#141E30';
        document.getElementById('edit-cor-picker').value = '#141E30';

        setInputAndPreview('edit-logo', 'edit-logo-preview', '');
        setInputAndPreview('edit-foto-social', 'edit-foto-social-preview', '');

        document.getElementById('edit-chefe').value = '';

        document.getElementById('members-list').innerHTML = '<li style="color: #999;">Salve a equipe primeiro para adicionar membros.</li>';
        document.querySelector('#editModal h2').innerText = 'Nova Equipe';

        const membersSection = document.querySelector('#editModal > div > div:last-child');
        if (membersSection) membersSection.style.display = 'none';

        modal.style.display = 'flex';
    }

    function setInputAndPreview(inputId, imgId, value) {
        const input = document.getElementById(inputId);
        if (input) input.value = value;
        const img = document.getElementById(imgId);
        if (img) {
            img.src = value || '/images/logo-campeonato.png';
            img.style.display = 'inline';
        }
    }



    function removePilot(pilotoId) {
        if (confirm('Remover este piloto da equipe?')) {
            document.getElementById('remove-piloto-id').value = pilotoId;
            document.getElementById('removePilotForm').submit();
        }
    }

    function closeEditModal() {
        modal.style.display = 'none';
    }

    // Initialize
    if (teams && teams.length > 0) {
        renderTeam(currentIndex);
    } else {
        if (!jsonError && container) container.innerHTML = '<div class="no-teams">Nenhuma equipe cadastrada.</div>';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === "ArrowLeft") prevSlide();
        if (event.key === "ArrowRight") nextSlide();
    });
</script>

<?php require_once 'includes/footer.php'; ?>