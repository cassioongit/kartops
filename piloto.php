<?php
/**
 * =====================================================
 * PERFIL DO PILOTO - KartOps
 * =====================================================
 */
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: pilotos.php');
    exit;
}

$pageTitle = 'Perfil do Piloto';
$additionalCSS = ['/css/piloto_individual.css'];
require_once 'includes/header.php';

if (!$usuario) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

    // 1. DADOS BASE DO PILOTO E EQUIPE
    $stmt = $pdo->prepare("
        SELECT p.*, e.nome as equipe_nome, e.cor as equipe_cor, e.imagem as equipe_logo, c.nome as categoria_nome 
        FROM pilotos p 
        LEFT JOIN equipes e ON p.equipe_id = e.id 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $piloto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$piloto) {
        throw new Exception("Piloto não encontrado.");
    }

    $catNome = $piloto['categoria_nome'] ?? '';

    // 2. BUSCAR ESTATÍSTICAS REAIS (RANK, PONTOS, VITÓRIAS) DA HOME
    require_once 'includes/classificacao_helper.php';
    // O helper aceita "Master" ou "Challenger" (suporta curtos)
    $filterParam = (stripos($catNome, 'Master') !== false) ? 'Master' : 'Challenger';
    $rankingGlobal = getClassificationData($pdo, $filterParam);

    $rankDisplay = '-';
    $ptsTotais = 0;
    $vitoriasTotais = 0;

    // Buscar o piloto na lista de classificacao global retornada
    foreach ($rankingGlobal as $rankPos) {
        if ($rankPos['id'] === $id) {
            $rankDisplay = $rankPos['pos'];
            $ptsTotais = $rankPos['total'];
            $vitoriasTotais = $rankPos['vitorias'];
            break;
        }
    }

    // 3. ESTATÍSTICAS EXTRAS (Melhores Resultados Top 6, Podios)
    $stmtBest = $pdo->prepare("SELECT posicao, COUNT(*) as qtd FROM resultados WHERE piloto_id = ? AND posicao >= 1 AND posicao <= 6 AND desclassificado = 0 GROUP BY posicao");
    $stmtBest->execute([$id]);
    $bestRaw = $stmtBest->fetchAll(PDO::FETCH_ASSOC);
    $bestMap = ['1' => 0, '2' => 0, '3' => 0, '4_6' => 0];
    foreach ($bestRaw as $row) {
        $pos = (int)$row['posicao'];
        if ($pos === 1) $bestMap['1'] += $row['qtd'];
        elseif ($pos === 2) $bestMap['2'] += $row['qtd'];
        elseif ($pos === 3) $bestMap['3'] += $row['qtd'];
        else $bestMap['4_6'] += $row['qtd'];
    }
    // Total de Pódios Top 6
    $totalPodiums = $bestMap['1'] + $bestMap['2'] + $bestMap['3'] + $bestMap['4_6'];

    // 4. LÓGICA DE NAVEGAÇÃO PROX/ANTERIOR
    $stmtAll = $pdo->query("
        SELECT id FROM pilotos 
        ORDER BY 
            categoria_id ASC, 
            CASE WHEN apelido IS NOT NULL AND apelido != '' THEN apelido ELSE nome END ASC
    ");
    $allPilotIds = $stmtAll->fetchAll(PDO::FETCH_COLUMN);
    $currentIndex = array_search($id, $allPilotIds);
    $prevPilotId = null;
    $nextPilotId = null;
    
    if ($currentIndex !== false) {
        if ($currentIndex > 0) {
            $prevPilotId = $allPilotIds[$currentIndex - 1];
        }
        if ($currentIndex < count($allPilotIds) - 1) {
            $nextPilotId = $allPilotIds[$currentIndex + 1];
        }
    }

// Foto Padrão e Cache Buster
$fotoRaw = empty($piloto['foto']) ? '/images/turtle-driver.png' : $piloto['foto'];
$fotoRaw = preg_replace('/\?.*$/', '', $fotoRaw);
$fotoFinal = $fotoRaw . '?v=' . time();

// Separação de Nomes
$fullName = trim($piloto['nome']);
$firstNameParts = explode(' ', $fullName);
$firstName = array_shift($firstNameParts); // Pega o primeiro nome
$lastNameFallback = implode(' ', $firstNameParts); // O restante

// Se tem apelido, usa ele gigante, senão usa o sobrenome gigante (ou o único nome).
$giantName = !empty($piloto['apelido']) ? $piloto['apelido'] : ($lastNameFallback ?: $firstName);

// Cor da Equipe (Fallback Ciano)
$teamColor = !empty($piloto['equipe_cor']) ? $piloto['equipe_cor'] : '#00b9ff';

// 5. PERMISSÃO DE EDIÇÃO
$userRole = strtolower($_SESSION['user_role'] ?? 'visitante');
$isAdmin = in_array($userRole, ['admin', 'owner']);
$isLinkedPilot = (!empty($usuario['id_piloto']) && $usuario['id_piloto'] === $id);
$canEdit = $isAdmin || $isLinkedPilot;
?>

<div class="piloto-individual-wrapper" style="--pilot-team-color: <?= $teamColor ?>;">
    <!-- Setas de Navegação Fixas -->
    <?php if ($prevPilotId): ?>
        <a href="piloto.php?id=<?= $prevPilotId ?>" class="pilot-nav-arrow prev-pilot" title="Piloto Anterior">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 19l-7-7 7-7M19 19l-7-7 7-7" />
            </svg>
        </a>
    <?php endif; ?>

    <?php if ($nextPilotId): ?>
        <a href="piloto.php?id=<?= $nextPilotId ?>" class="pilot-nav-arrow next-pilot" title="Próximo Piloto">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </a>
    <?php endif; ?>

    <!-- Botão de Voltar -->
    <div class="piloto-header-actions">
        <a href="pilotos.php" class="btn-back-pilotos" title="Voltar para Pilotos">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
    </div>

    <!-- 1. Hero / Banner Principal -->
    <div class="pilot-hero-banner">
        
        <!-- Novo Fundo Diagonal Estilo Formula E -->
        <div class="hero-diagonal-bg"></div>

        <div class="hero-content-inner">
            <!-- Texto à Esquerda -->
            <div class="hero-text-side">
                <h1 class="h-giant-name">
                    <?= htmlspecialchars($giantName) ?>
                    <?php if ($canEdit): ?>
                        <a href="editar_piloto.php?id=<?= $id ?>" class="btn-edit-pilot-discrete" title="Editar Perfil">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </h1>
                <span class="h-team-name" style="color: <?= $teamColor ?>;">
                    <?= htmlspecialchars($id === '19c03d64-f972-11f0-8ead-eef805f27145' ? 'RUIMLLIAMS' : ($piloto['equipe_nome'] ?? 'SEM EQUIPE')) ?>
                </span>
            </div>

            <!-- Foto à Direita -->
            <div class="hero-photo-side">
                <div class="hero-photo-mask">
                    <img src="<?= htmlspecialchars($fotoFinal) ?>" alt="Foto" class="hero-pilot-img" crossorigin="anonymous">
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Cabeçalho de Identificação e 3. Estatísticas  -->
    <div class="pilot-details-container">
        
        <div class="identification-block-v2">
            <?php if (!empty($piloto['bio'])): ?>
            <div class="pilot-bio-box">
                <p>"<?= nl2br(htmlspecialchars($piloto['bio'])) ?>"</p>
            </div>
            <?php endif; ?>

            <?php if (!empty($piloto['instagram'])): ?>
            <div class="pilot-social-box">
                <a href="https://instagram.com/<?= str_replace('@', '', $piloto['instagram']) ?>" target="_blank" class="pilot-social-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                    <span>@<?= str_replace('@', '', htmlspecialchars($piloto['instagram'])) ?></span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- 3. Bloco: Estatísticas da Temporada Atual -->
        <div class="stats-season-block">
            <h4 class="stats-title">ESTATÍSTICAS DA TEMPORADA</h4>
            <div class="stats-grid-4">
                <div class="stat-card">
                    <span class="s-label">POSIÇÃO GERAL</span>
                    <span class="s-value"><?= $rankDisplay ?>º</span>
                </div>
                <div class="stat-card">
                    <span class="s-label">PONTOS OBTIDOS</span>
                    <span class="s-value"><?= $ptsTotais ?></span>
                </div>
                <?php 
                $hasTrophies = ($bestMap['1'] > 0 || $bestMap['2'] > 0 || $bestMap['3'] > 0);
                if ($hasTrophies): 
                ?>
                <div class="stat-card stat-card-trophies">
                    <span class="s-label">MELHORES RESULTADOS</span>
                    <div class="trophies-container">
                        <?php 
                        // 1º Lugar
                        for($i=0; $i<$bestMap['1']; $i++) echo '<img src="images/places/primeiro.png" class="place-icon first-place" title="1º Lugar">';
                        // 2º Lugar
                        for($i=0; $i<$bestMap['2']; $i++) echo '<img src="images/places/segundo.png" class="place-icon second-place" title="2º Lugar">';
                        // 3º Lugar
                        for($i=0; $i<$bestMap['3']; $i++) echo '<img src="images/places/terceiro.png" class="place-icon third-place" title="3º Lugar">';
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($totalPodiums > 0): ?>
                <div class="stat-card">
                    <span class="s-label">PÓDIOS (TOP 6)</span>
                    <span class="s-value"><?= $totalPodiums ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

</div>

<!-- Toast de Incentivo Instagram -->
<div id="instaToast" class="social-onboarding-toast">
    <i class="fab fa-instagram"></i>
    <span>Você pode cadastrar seu Instagram no seu perfil de piloto! 🏎️💨</span>
    <div class="btn-close-toast" onclick="closeInstaToast()">
        <i class="fas fa-times"></i>
    </div>
</div>

<!-- Calcular RGB para rgba nas variáveis e lógica de Cookie -->
<script>
    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? parseInt(result[1], 16) + ', ' + parseInt(result[2], 16) + ', ' + parseInt(result[3], 16) : null;
    }
    const teamColor = "<?= $teamColor ?>";
    const rgbStr = hexToRgb(teamColor) || '0, 185, 255';
    document.querySelector('.piloto-individual-wrapper').style.setProperty('--pilot-team-rgb-val', rgbStr);

    // Lógica do Toast de Instagram
    function getCookie(name) {
        let dc = document.cookie;
        let prefix = name + "=";
        let begin = dc.indexOf("; " + prefix);
        if (begin == -1) {
            begin = dc.indexOf(prefix);
            if (begin != 0) return null;
        } else {
            begin += 2;
        }
        let end = document.cookie.indexOf(";", begin);
        if (end == -1) end = dc.length;
        return decodeURI(dc.substring(begin + prefix.length, end));
    }

    function closeInstaToast() {
        document.getElementById('instaToast').classList.remove('show');
        // Define o cookie para não mostrar novamente por 7 dias
        const d = new Date();
        d.setTime(d.getTime() + (7 * 24 * 60 * 60 * 1000));
        let expires = "expires=" + d.toUTCString();
        document.cookie = "kartops_social_prompt=1;" + expires + ";path=/";
    }

    window.onload = function() {
        if (!getCookie('kartops_social_prompt')) {
            setTimeout(() => {
                document.getElementById('instaToast').classList.add('show');
            }, 2000);
        }
    };
</script>

<?php require_once 'includes/footer.php'; ?>
