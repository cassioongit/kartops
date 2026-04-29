<?php
/**
 * =====================================================
 * GESTÃO DE ETAPAS - KartOps
 * =====================================================
 * Página para gerenciar etapas do campeonato
 * Acesso: Admin, Colaborador e Owner (apenas Admin e Owner podem editar)
 */

// Configurações da página
$pageTitle = 'Etapas';
$additionalCSS = ['/css/etapas.css'];

// Incluir header e configurações
require_once 'includes/header.php';
require_once 'includes/patrocinadores.php';

// Verificar se usuário está logado (incluindo visitantes)
if (!$usuario) {
    header('Location: index.php');
    exit;
}

// Verificar se pode editar (apenas Admin e Owner)
// Visitantes e usuários comuns podem visualizar, mas não editar
$canEdit = ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner');

// Buscar todas as etapas
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, nome, data, kartodromo, hora, patrocinador, tipo_etapa, 
               local_variavel, data_variavel, criado_em, atualizado_em 
        FROM etapas 
        ORDER BY data ASC
    ");
    $stmt->execute();
    $etapas = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erro ao carregar etapas: " . $e->getMessage();
    $etapas = [];
}

// Buscar vencedores (1º lugar) de cada etapa passada, por categoria
$vencedores = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT r.etapa_id, r.categoria, 
               COALESCE(NULLIF(p.apelido, ''), p.nome) as piloto_nome
        FROM resultados r
        JOIN pilotos p ON r.piloto_id = p.id
        WHERE r.posicao = 1 AND r.desclassificado = 0
        ORDER BY r.categoria ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $etapaId = $row['etapa_id'];
        $cat = $row['categoria'];
        if (!isset($vencedores[$etapaId][$cat])) {
            $vencedores[$etapaId][$cat] = [];
        }
        $vencedores[$etapaId][$cat][] = $row['piloto_nome'];
    }
} catch (PDOException $e) {
    // Silently fail — winners are optional
}

// Função para formatar tipo de etapa para classe CSS
function getTipoClass($tipo)
{
    $tipo = strtolower($tipo);
    if (strpos($tipo, 'regular') !== false)
        return 'type-regular';
    if (strpos($tipo, 'superpole') !== false)
        return 'type-superpole';
    if (strpos($tipo, 'grid') !== false || strpos($tipo, 'invertido') !== false)
        return 'type-grid-invertido';
    if (strpos($tipo, 'endurance') !== false)
        return 'type-endurance';
    return 'type-tbd';
}

// Lista de kartódromos únicos para filtro
$kartodromos = array_unique(array_column($etapas, 'kartodromo'));
sort($kartodromos);

// Formatadores de data em português (substitui strftime deprecado)
$formatterWeekday = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE');
$formatterMonth = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMM');
?>

<!-- Gestão de Etapas Content -->
<div class="etapas-management-wrapper">
    <div class="page-header">
        <div class="header-left">
            <a href="home.php" class="btn-back-home" title="Voltar para Home">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="page-title">🏁 Etapas</h1>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <button onclick="openAddEtapaModal()" class="btn-primary">
                ➕ Nova Etapa
            </button>
        <?php endif; ?>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filtros e Busca -->
    <div class="filters-container">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 Buscar por nome, kartódromo ou patrocinador..."
                onkeyup="filterEtapas()">
        </div>
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all" onclick="filterByKartodromo('all')">
                Todos (<?= count($etapas) ?>)
            </button>
            <?php foreach ($kartodromos as $k): ?>
                <button class="filter-btn" data-filter="<?= htmlspecialchars($k) ?>"
                    onclick="filterByKartodromo('<?= htmlspecialchars($k) ?>')">
                    <?= htmlspecialchars(str_replace('Kartódromo ', '', $k)) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="filter-buttons" style="margin-top: 10px;">
            <button class="filter-btn-tipo active" data-tipo="all" onclick="filterByTipo('all')">Todos os Tipos</button>
            <button class="filter-btn-tipo" data-tipo="regular" onclick="filterByTipo('regular')">Regular</button>
            <button class="filter-btn-tipo" data-tipo="superpole" onclick="filterByTipo('superpole')">Superpole</button>
            <button class="filter-btn-tipo" data-tipo="grid" onclick="filterByTipo('grid')">Grid Invertido</button>
            <button class="filter-btn-tipo" data-tipo="endurance" onclick="filterByTipo('endurance')">Endurance</button>
        </div>
    </div>

    <!-- Toggle A Realizar / Realizadas -->
    <div class="status-toggle">
        <button class="status-btn active" id="btn-upcoming" onclick="filterByStatus('upcoming')">
            🏁 A Realizar
        </button>
        <button class="status-btn" id="btn-past" onclick="filterByStatus('past')">
            ✅ Realizadas
        </button>
    </div>

    <!-- Cards de Etapas -->
    <div class="etapas-cards" id="etapasContainer">
        <?php if (empty($etapas)): ?>
            <div class="empty-state">
                <div class="empty-icon">🏁</div>
                <h3>Nenhuma etapa cadastrada</h3>
                <p>Adicione a primeira etapa do campeonato!</p>
            </div>
        <?php else: ?>
            <?php foreach ($etapas as $index => $etapa):
                $dataObj = new DateTime($etapa['data']);
                $isPast = $dataObj < new DateTime('today');
                $isToday = $dataObj->format('Y-m-d') === date('Y-m-d');
                $weekdayName = ucfirst($formatterWeekday->format($dataObj));
                $monthName = strtoupper($formatterMonth->format($dataObj));
                // Dados do patrocinador e link do Maps
                $sponsor = getSponsorInfo($etapa['patrocinador'] ?? 'TBD');
                $mapsUrl = getGoogleMapsUrl($etapa['kartodromo']);
                ?>
                <div class="etapa-card <?= $isPast ? 'past' : '' ?> <?= $isToday ? 'today' : '' ?>"
                    data-kartodromo="<?= htmlspecialchars($etapa['kartodromo']) ?>"
                    data-tipo="<?= strtolower($etapa['tipo_etapa']) ?>"
                    data-nome="<?= strtolower(htmlspecialchars($etapa['nome'])) ?>"
                    data-patrocinador="<?= strtolower(htmlspecialchars($etapa['patrocinador'] ?? '')) ?>">

                    <!-- Header do Card com Tipo -->
                    <div class="card-type-header <?= getTipoClass($etapa['tipo_etapa']) ?>">
                        <span class="etapa-number">#<?= $index + 1 ?></span>
                        <span class="etapa-tipo"><?= htmlspecialchars($etapa['tipo_etapa']) ?></span>
                    </div>

                    <!-- Conteúdo Principal -->
                    <div class="card-body">
                        <!-- Data -->
                        <div class="etapa-date-block">
                            <span class="weekday"><?= $weekdayName ?></span>
                            <span class="day"><?= $dataObj->format('d') ?></span>
                            <span class="month"><?= $monthName ?></span>
                            <span class="year"><?= $dataObj->format('Y') ?></span>
                        </div>

                        <!-- Informações -->
                        <div class="etapa-info">
                            <h3 class="etapa-nome"><?= htmlspecialchars($etapa['nome']) ?></h3>

                            <div class="etapa-details">
                                <div class="detail-item">
                                    <span class="detail-icon">📍</span>
                                    <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener noreferrer"
                                        class="kartodromo-link">
                                        <?= htmlspecialchars($etapa['kartodromo']) ?>
                                    </a>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-icon">🕐</span>
                                    <span><?= date('H:i', strtotime($etapa['hora'])) ?></span>
                                </div>
                            </div>

                            <!-- Logo do Patrocinador -->
                            <?php if ($etapa['patrocinador'] && $etapa['patrocinador'] !== 'TBD'): ?>
                                <div class="etapa-sponsor">
                                    <?php if ($sponsor['site']): ?>
                                        <a href="<?= htmlspecialchars($sponsor['site']) ?>" target="_blank" rel="noopener noreferrer"
                                            class="sponsor-link">
                                            <img src="<?= htmlspecialchars($sponsor['logo']) ?>"
                                                alt="<?= htmlspecialchars($etapa['patrocinador']) ?>" class="sponsor-logo">
                                        </a>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($sponsor['logo']) ?>"
                                            alt="<?= htmlspecialchars($etapa['patrocinador']) ?>" class="sponsor-logo">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Badges -->
                            <div class="etapa-badges">
                                <?php if ($etapa['data_variavel']): ?>
                                    <span class="badge badge-warning">📅 Data variável</span>
                                <?php endif; ?>
                                <?php if ($etapa['local_variavel']): ?>
                                    <span class="badge badge-warning">📍 Local variável</span>
                                <?php endif; ?>
                                <?php if ($isToday): ?>
                                    <span class="badge badge-today">🔴 HOJE</span>
                                <?php endif; ?>
                                <?php if ($isPast): ?>
                                    <span class="badge badge-past">✓ Realizada</span>
                                <?php endif; ?>
                            </div>

                            <!-- Vencedores (etapas passadas com resultados) -->
                            <?php if ($isPast && !empty($vencedores[$etapa['id']])): ?>
                                <div class="etapa-winners">
                                    <span class="winners-title">🏆 Vencedores</span>
                                    <?php foreach ($vencedores[$etapa['id']] as $cat => $pilotos): ?>
                                        <div class="winner-item">
                                            <span class="winner-category"><?= htmlspecialchars($cat) ?></span>
                                            <span class="winner-name"><?= htmlspecialchars(implode(', ', $pilotos)) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Ações -->
                    <?php if ($canEdit): ?>
                        <div class="card-actions">
                            <button onclick="viewEtapa('<?= $etapa['id'] ?>')" class="btn-action btn-view" title="Visualizar">
                                👁️
                            </button>
                            <button onclick="editEtapa('<?= $etapa['id'] ?>')" class="btn-action btn-edit" title="Editar">
                                ✏️
                            </button>
                            <button onclick="deleteEtapa('<?= $etapa['id'] ?>', '<?= htmlspecialchars($etapa['nome']) ?>')"
                                class="btn-action btn-delete" title="Excluir">
                                🗑️
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="card-actions">
                            <button onclick="viewEtapa('<?= $etapa['id'] ?>')" class="btn-action btn-view" title="Visualizar">
                                👁️
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>


</div>

<!-- Modal: Adicionar/Editar Etapa -->
<div id="etapaModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="modalTitle">Adicionar Etapa</h2>
            <button onclick="closeEtapaModal()" class="modal-close">✕</button>
        </div>
        <form id="etapaForm" onsubmit="saveEtapa(event)">
            <input type="hidden" id="etapaId" name="etapaId">

            <div class="form-row">
                <div class="form-group">
                    <label for="etapaNome">Nome da Etapa *</label>
                    <input type="text" id="etapaNome" name="nome" required placeholder="Ex: AINEXT MYRMEX">
                </div>
                <div class="form-group">
                    <label for="etapaPatrocinador">Patrocinador</label>
                    <input type="text" id="etapaPatrocinador" name="patrocinador" placeholder="Ex: AINEXT MYRMEX">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="etapaData">Data *</label>
                    <input type="date" id="etapaData" name="data" required>
                </div>
                <div class="form-group">
                    <label for="etapaHora">Horário *</label>
                    <input type="time" id="etapaHora" name="hora" required value="14:00">
                </div>
            </div>

            <div class="form-group">
                <label for="etapaKartodromo">Kartódromo *</label>
                <select id="etapaKartodromo" name="kartodromo" required onchange="toggleKartodromoCustom()">
                    <option value="">Selecione o kartódromo</option>
                    <option value="Kartódromo Nova Odessa">Kartódromo Nova Odessa</option>
                    <option value="Kartódromo San Marino Paulínia">Kartódromo San Marino Paulínia</option>
                    <option value="Kartódromo Granja Viana">Kartódromo Granja Viana</option>
                    <option value="Kartódromo Ayrton Senna Interlagos">Kartódromo Ayrton Senna Interlagos</option>
                    <option value="__outro__">Outro...</option>
                </select>
                <input type="text" id="etapaKartodromoCustom" placeholder="Digite o nome do kartódromo"
                    style="display: none; margin-top: 8px;">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="etapaTipo">Tipo de Etapa *</label>
                    <select id="etapaTipo" name="tipo_etapa" required>
                        <option value="Etapa Regular">Etapa Regular</option>
                        <option value="Superpole">Superpole</option>
                        <option value="Grid Invertido">Grid Invertido</option>
                        <option value="Endurance">Endurance</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-check">
                    <label class="check-label">
                        <input type="checkbox" id="etapaDataVariavel" name="data_variavel">
                        <span>📅 Data variável (pode mudar)</span>
                    </label>
                </div>
                <div class="form-group form-check">
                    <label class="check-label">
                        <input type="checkbox" id="etapaLocalVariavel" name="local_variavel">
                        <span>📍 Local variável (pode mudar)</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeEtapaModal()" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Visualizar Etapa -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalhes da Etapa</h2>
            <button onclick="closeViewModal()" class="modal-close">✕</button>
        </div>
        <div id="viewModalContent" class="view-content">
            <!-- Conteúdo será preenchido via JavaScript -->
        </div>
        <div class="modal-footer">
            <button onclick="closeViewModal()" class="btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
    // Dados para o JavaScript externo
    const etapasData = <?= json_encode($etapas) ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
</script>
<script src="/js/etapas_admin.js?v=<?= time() ?>"></script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>