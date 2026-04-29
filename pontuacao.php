<?php
/**
 * GESTÃO DE PONTUAÇÃO - KartOps
 * =====================================================
 * Página para gerenciar a tabela de pontuação
 */


require_once 'includes/auth_session.php';

// Auth check (Logic Only)
if (!$usuario) {
    header('Location: index.php');
    exit;
}
if (!in_array($usuario['role'], ['Admin', 'Colaborador', 'Owner', 'Usuário'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Tabela de Pontuação';
$additionalCSS = ['/css/pontuacao.css'];

require_once 'includes/header.php';

$canEdit = ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner');

// Buscar dados
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM tabela_pontuacao ORDER BY categoria, posicao ASC");
    $stmt->execute();
    $pontuacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao carregar dados: " . $e->getMessage();
    $pontuacoes = [];
}

// Categorias únicas para o filtro
$categorias = array_unique(array_column($pontuacoes, 'categoria'));
sort($categorias);
?>



<div class="pontuacao-container">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <a href="home.php" class="btn-back-home" title="Voltar para Home">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="page-title">📋 Tabela de Pontuação</h1>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <button onclick="openModal()" class="btn-primary">➕ Nova Regra</button>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="filter-bar">
        <button class="btn-filter active" onclick="filterTable('all', this)">Todos</button>
        <?php foreach ($categorias as $cat): ?>
            <button class="btn-filter" onclick="filterTable('<?= htmlspecialchars($cat) ?>', this)">
                <?= htmlspecialchars($cat) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Tabela -->
    <div style="overflow-x: auto;">
        <table class="data-table" id="pontuacaoTable">
            <thead>
                <tr>
                    <th>Cat.</th>
                    <th>Peso</th>
                    <th>Pos.</th>
                    <th>1º Sem</th>
                    <th>Jul</th>
                    <th>Ago</th>
                    <th>Set</th>
                    <th>Out</th>
                    <th>Nov</th>
                    <th>Dez</th>
                    <?php if ($canEdit): ?>
                        <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pontuacoes as $p): ?>
                    <tr class="row-item" data-category="<?= htmlspecialchars($p['categoria']) ?>">
                        <td><span class="badge-cat cat-<?= htmlspecialchars($p['categoria']) ?>">
                                <?= htmlspecialchars($p['categoria']) ?>
                            </span></td>
                        <td><span class="peso-badge peso-<?= $p['peso'] ?? 1 ?>"><?= $p['peso'] ?? 1 ?>x</span></td>
                        <td><b>
                                <?= $p['posicao'] ?>º
                            </b></td>
                        <td>
                            <?= $p['primeiro_semestre'] ?>
                        </td>
                        <td>
                            <?= $p['julho'] ?>
                        </td>
                        <td>
                            <?= $p['agosto'] ?>
                        </td>
                        <td>
                            <?= $p['setembro'] ?>
                        </td>
                        <td>
                            <?= $p['outubro'] ?>
                        </td>
                        <td>
                            <?= $p['novembro'] ?>
                        </td>
                        <td>
                            <?= $p['dezembro'] ?>
                        </td>
                        <?php if ($canEdit): ?>
                            <td>
                                <button class="action-btn" onclick='editItem(<?= json_encode($p) ?>)' title="Editar">✏️</button>
                                <button class="action-btn" onclick="deleteItem(<?= $p['id'] ?>)" title="Excluir">🗑️</button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="modalForm" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle" style="margin-bottom: 20px;">Adicionar Regra</h2>
        <form id="pontuacaoForm" onsubmit="saveItem(event)">
            <input type="hidden" id="p_id" name="id">
            <input type="hidden" name="action" id="formAction">

            <div class="form-grid">
                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" name="categoria" id="p_categoria" required placeholder="Ex: Master">
                </div>
                <div class="form-group">
                    <label>Peso (Multiplicador)</label>
                    <select name="peso" id="p_peso" required>
                        <option value="1">1x (Básico)</option>
                        <option value="2">2x (Intermediário)</option>
                        <option value="3" selected>3x (Avançado)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Posição</label>
                    <input type="number" name="posicao" id="p_posicao" required min="1">
                </div>

                <div class="form-group">
                    <label>1º Semestre</label>
                    <input type="number" name="primeiro_semestre" id="p_sem1" value="0">
                </div>
                <div class="form-group">
                    <label>Julho</label>
                    <input type="number" name="julho" id="p_jul" value="0">
                </div>
                <div class="form-group">
                    <label>Agosto</label>
                    <input type="number" name="agosto" id="p_ago" value="0">
                </div>
                <div class="form-group">
                    <label>Setembro</label>
                    <input type="number" name="setembro" id="p_set" value="0">
                </div>
                <div class="form-group">
                    <label>Outubro</label>
                    <input type="number" name="outubro" id="p_out" value="0">
                </div>
                <div class="form-group">
                    <label>Novembro</label>
                    <input type="number" name="novembro" id="p_nov" value="0">
                </div>
                <div class="form-group full-width">
                    <label>Dezembro</label>
                    <input type="number" name="dezembro" id="p_dez" value="0">
                </div>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script src="/js/pontuacao_admin.js?v=<?= time() ?>"></script>

<?php require_once 'includes/footer.php'; ?>