<?php
/**
 * =====================================================
 * LISTAGEM DE PILOTOS - KartOps (Estilo Formula E)
 * =====================================================
 */
require_once 'config/config.php';
require_once 'includes/auth_session.php'; // Inicia a sessão corretamente

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$canCreate = (isset($_SESSION['user_role']) && in_array(strtolower($_SESSION['user_role']), ['admin', 'owner']));

$pageTitle = 'Pilotos';
$additionalCSS = ['/css/pilotos.css'];
require_once 'includes/header.php';

$msg = '';

try {
    $pdo = getDBConnection();

    // 2. BUSCAR TODOS OS PILOTOS
    $stmt = $pdo->query("
        SELECT p.*, e.nome as equipe_nome, e.cor as equipe_cor, e.imagem as equipe_logo, c.nome as categoria_nome 
        FROM pilotos p 
        LEFT JOIN equipes e ON p.equipe_id = e.id 
        LEFT JOIN categorias c ON p.categoria_id = c.id
        ORDER BY 
            p.categoria_id ASC, 
            CASE WHEN p.apelido IS NOT NULL AND p.apelido != '' THEN p.apelido ELSE p.nome END ASC
    ");
    $allPilotsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. AGRUPAR POR CATEGORIA
    $categoriasPilotos = [];
    foreach ($allPilotsRaw as $pilot) {
        $catNomeRaw = $pilot['categoria_nome'] ?? 'SEM CATEGORIA';
        if (stripos($catNomeRaw, 'Master') !== false) {
            $catLabel = 'Master';
        } elseif (stripos($catNomeRaw, 'Challenge') !== false) {
            $catLabel = 'Challengers';
        } else {
            $catLabel = $catNomeRaw;
        }
        
        $catNome = strtoupper($catLabel);
        
        if (!isset($categoriasPilotos[$catNome])) {
            $categoriasPilotos[$catNome] = [];
        }
        $categoriasPilotos[$catNome][] = $pilot;
    }

} catch (PDOException $e) {
    echo "<div style='color: white; padding: 100px;'>Erro de Banco de Dados: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
?>

<div class="pilotos-wrapper">

    <!-- Ações de Cabeçalho (Não fixadas no topo) -->
    <div class="pilotos-header-actions">
        <a href="home.php" class="btn-back-pilotos" title="Voltar para Home">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar por nome..." oninput="filterPilots()">
        </div>

        <?php if ($canCreate): ?>
            <div class="top-actions">
                <a href="cadastrar_piloto.php" class="btn-add-pilot" title="Adicionar Piloto" style="text-decoration:none">+</a>
            </div>
        <?php else: ?>
            <div class="top-actions placeholder-action"></div>
        <?php endif; ?>
    </div>


    <!-- LISTAGEM POR CATEGORIA -->
    <div id="pilotsContainer" style="width: 100%; max-width: 1200px;">
        <?php if (empty($categoriasPilotos)): ?>
            <div class="no-pilots">Nenhum piloto cadastrado no momento.</div>
        <?php else: ?>

            <?php foreach ($categoriasPilotos as $catName => $pilots): ?>
                <div class="category-section pilot-group">
                    <h2 class="category-title">PILOTOS <?= htmlspecialchars($catName) ?></h2>
                    
                    <div class="pilots-grid">
                        <?php foreach ($pilots as $pilot): 
                            // Tratamento de Nome
                            $fullName = $pilot['nome'];
                            $firstNameParts = explode(' ', trim($fullName));
                            $firstName = array_shift($firstNameParts); // Pega o primeiro nome
                            $lastNameFallback = implode(' ', $firstNameParts); // O restante
                            
                            $giantName = !empty($pilot['apelido']) ? $pilot['apelido'] : ($lastNameFallback ?: 'PILOTO');
                            $teamColor = !empty($pilot['equipe_cor']) ? $pilot['equipe_cor'] : '#333333';
                            $teamLogo = !empty($pilot['equipe_logo']) ? $pilot['equipe_logo'] : false;
                        ?>

                            <a href="piloto.php?id=<?= $pilot['id'] ?>" class="pilot-list-card pilot-filter-item" style="--team-color: <?= $teamColor ?>;">
                                <!-- Detalhe Diagonal de Fundo -->
                                <div class="bg-clip"></div>

                                <!-- Rank Number - placeholder pois precisariamos do rank global -->
                                <!-- <span class="pilot-number">X</span> -->

                                <div class="pilot-content">
                                    <div class="pilot-info">
                                        <span class="pilot-first-name js-first-name"><?= htmlspecialchars($firstName) ?></span>
                                        <span class="pilot-last-name js-giant-name"><?= htmlspecialchars($giantName) ?></span>
                                        <span class="pilot-team-name" style="color: <?= $teamColor ?>;">
                                            <?= htmlspecialchars($pilot['equipe_nome'] ?? 'SEM EQUIPE') ?>
                                        </span>
                                    </div>
                                    <!-- Foto do Piloto focando no top -->
                                    <?php if (!empty($pilot['foto'])): ?>
                                        <div class="pilot-photo-wrapper">
                                            <img src="<?= htmlspecialchars($pilot['foto']) ?>" class="pilot-photo-small" alt="Foto">
                                        </div>
                                    <?php else: ?>
                                        <!-- Placeholder se não tiver foto -->
                                        <div class="pilot-photo-wrapper">
                                            <img src="/images/turtle-driver.png" class="pilot-photo-small" style="opacity: 0.5; mix-blend-mode: luminosity;" alt="Sem foto">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>

                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

</div>

<!-- JavaScript para o Filtro de Busca -->
<script>
function filterPilots() {
    const input = document.getElementById('searchInput');
    const filterText = input.value.toLowerCase().trim();
    const sections = document.querySelectorAll('.category-section');

    sections.forEach(section => {
        let hasVisiblePilots = false;
        const pilotCards = section.querySelectorAll('.pilot-filter-item');

        pilotCards.forEach(card => {
            const firstName = card.querySelector('.js-first-name')?.innerText.toLowerCase() || '';
            const giantName = card.querySelector('.js-giant-name')?.innerText.toLowerCase() || '';

            if (firstName.includes(filterText) || giantName.includes(filterText)) {
                card.style.display = 'flex';
                hasVisiblePilots = true;
            } else {
                card.style.display = 'none';
            }
        });

        section.style.display = hasVisiblePilots ? 'block' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
