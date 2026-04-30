<?php
/**
 * =====================================================
 * GESTÃO DE CATEGORIAS - KartOps
 * =====================================================
 * Listagem e CRUD de categorias (F4 Light, Heavy, Speed, etc)
 */

$pageTitle = 'Gestão de Categorias';
// Using inline CSS within a style block for the specific login-like feel or repurposing login css + custom
// But user requested "Maintenance Style like Login".
// We can use the same CSS structure as the login page or include a specific CSS.
// Let's stick to the requested logic primarily.

require_once 'includes/header.php';

// Auth Check & Role Enforcement
if (!$usuario || ($usuario['role'] !== 'Admin' && $usuario['role'] !== 'Owner')) {
    // Tela de "Acesso Não Autorizado" com Redirecionamento
    ?>
    <div style="height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; font-family: 'Inter', sans-serif;">
        <div style="font-size: 64px; margin-bottom: 20px;">🚫</div>
        <h1 style="color: #c62828; margin-bottom: 10px;">Acesso Não Autorizado</h1>
        <p style="color: #666; font-size: 18px; margin-bottom: 30px;">Você não tem permissão para acessar esta página.</p>
        <p style="color: #999;">Redirecionando para a Home em 3 segundos...</p>
        <script>
            setTimeout(function() {
                window.location.href = 'home.php';
            }, 3000);
        </script>
    </div>
    <?php
    require_once 'includes/footer.php'; // Close body/html
    exit;
}

$error = '';
$success = '';
$pdo = getDBConnection();

// =====================================================
// PROCESSAMENTO DE FORMULÁRIO (ADD / EDIT / DELETE)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        // -------------------------------------------------
        // ADICIONAR NOVA CATEGORIA
        // -------------------------------------------------
        if ($action === 'add') {
            $nome = trim($_POST['nome']);
            $logo = trim($_POST['logo']);

            if (empty($nome)) {
                $error = 'O nome da categoria é obrigatório.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categorias (nome, logo) VALUES (?, ?)");
                    $stmt->execute([$nome, $logo]);
                    $success = 'Categoria adicionada com sucesso!';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error = 'Já existe uma categoria com este nome.';
                    } else {
                        throw $e;
                    }
                }
            }
        }

        // -------------------------------------------------
        // EDITAR CATEGORIA
        // -------------------------------------------------
        elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $nome = trim($_POST['nome']);
            $logo = trim($_POST['logo']);

            if (empty($nome)) {
                $error = 'O nome da categoria é obrigatório.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categorias SET nome = ?, logo = ? WHERE id = ?");
                    $stmt->execute([$nome, $logo, $id]);
                    $success = 'Categoria atualizada com sucesso!';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error = 'Já existe uma categoria com este nome.';
                    } else {
                        throw $e;
                    }
                }
            }
        }

        // -------------------------------------------------
        // EXCLUIR CATEGORIA
        // -------------------------------------------------
        elseif ($action === 'delete') {
            $id = intval($_POST['id']);

            // Verificar se existem pilotos vinculados
            $check = $pdo->prepare("SELECT COUNT(*) FROM pilotos WHERE categoria_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $error = 'Não é possível excluir esta categoria pois existem pilotos vinculados a ela.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Categoria removida com sucesso!';
            }
        }

    } catch (PDOException $e) {
        error_log("[KartOps] Erro: " . $e->getMessage());
$error = 'Erro interno do servidor. Tente novamente mais tarde.';
    }
}

// =====================================================
// BUSCAR CATEGORIAS
// =====================================================
try {
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Erro ao carregar categorias.';
    $categorias = [];
}
?>

<style>
    /* Estilo "Maintenance" similar ao Login */
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        /* Padding top para o header fixo */
        padding-top: 80px; 
    }
    
    .maintenance-container {
        display: flex;
        justify-content: center;
        padding: 40px 20px;
    }

    .maintenance-card {
        background: white;
        border-radius: 24px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-width: 900px; /* Largura maior para a tabela */
        padding: 40px;
        animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header-section {
        text-align: center;
        margin-bottom: 30px;
    }

    .header-section h1 {
        font-size: 32px;
        color: #1a1a1a;
        margin-bottom: 10px;
    }

    .header-section p {
        color: #666;
    }
    
    .logo-badge {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin: 0 auto 20px;
    }

    /* Tabela estilizada */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .data-table th {
        text-align: left;
        padding: 15px;
        color: #666;
        font-weight: 600;
        border-bottom: 2px solid #f0f0f0;
    }

    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        color: #333;
    }
    
    .btn-add-main {
        background: linear-gradient(135deg, #00c853, #00e676);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s;
        text-decoration: none;
    }
    
    .btn-add-main:hover {
        transform: translateY(-2px);
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 5px;
        transition: all 0.2s;
    }
    
    .btn-edit { background: #e3f2fd; color: #1565c0; }
    .btn-edit:hover { background: #bbdefb; }
    
    .btn-delete { background: #ffebee; color: #c62828; }
    .btn-delete:hover { background: #ffcdd2; }

    /* Modal (reutilizando estilos simples) */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        display: none; align-items: center; justify-content: center;
        z-index: 2000;
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: white; border-radius: 20px; padding: 30px; width: 90%; max-width: 400px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
    .form-control { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; }
    
    .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; }
    .alert-success { background: #d4edda; color: #155724; }
    .alert-error { background: #f8d7da; color: #721c24; }

    .back-link {
        display: inline-block;
        margin-bottom: 20px;
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }
    .back-link:hover { text-decoration: underline; }
</style>

<div class="maintenance-container">
    <div class="maintenance-card">
        <a href="home.php" class="btn-back-home" title="Voltar para Home" style="margin-bottom: 20px; display: inline-flex;">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>

        <div class="header-section">
            <div class="logo-badge">🏷️</div>
            <h1>Gestão de Categorias</h1>
            <p>Cadastre e gerencie as categorias do campeonato</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <button class="btn-add-main" onclick="openModal('add')">
                + Nova Categoria
            </button>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Nome</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #999; padding: 30px;">Nenhuma categoria encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td>
                                    <?php if ($cat['logo']): ?>
                                        <img src="<?= htmlspecialchars($cat['logo']) ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">🏷️</div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 500; font-size: 16px;"><?= htmlspecialchars($cat['nome']) ?></td>
                                <td style="text-align: right;">
                                    <button class="btn-icon btn-edit" onclick='openModal("edit", <?= json_encode($cat) ?>)'>✏️</button>
                                    <button class="btn-icon btn-delete" onclick="confirmDelete(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome'], ENT_QUOTES) ?>')">🗑️</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Adicionar/Editar -->
<div id="categoryModal" class="modal-overlay">
    <div class="modal-content">
        <h2 id="modalTitle" style="margin-top: 0; margin-bottom: 20px;">Nova Categoria</h2>
        <form method="POST">
            <?= generateCsrfField() ?>
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="id" id="modalId">

            <div class="form-group">
                <label>Nome da Categoria</label>
                <input type="text" name="nome" id="nome" class="form-control" require placeholder="Ex: F4 Light">
            </div>
            
            <div class="form-group">
                <label>URL do Logo</label>
                <input type="text" name="logo" id="logo" class="form-control" placeholder="http://...">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn-icon" style="width: auto; padding: 10px 20px; background: #f0f0f0; color: #333;" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-add-main" style="border-radius: 8px;">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Excluir -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <h2 style="margin-top: 0; color: #c62828;">Excluir Categoria?</h2>
        <p>Tem certeza que deseja excluir <strong id="deleteName"></strong>?</p>
        
        <form method="POST">
            <?= generateCsrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn-icon" style="width: auto; padding: 10px 20px; background: #f0f0f0; color: #333;" onclick="closeDeleteModal()">Cancelar</button>
                <button type="submit" class="btn-icon" style="width: auto; padding: 10px 20px; background: #ffebee; color: #c62828;">Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(mode, data = null) {
        const modal = document.getElementById('categoryModal');
        const title = document.getElementById('modalTitle');
        const action = document.getElementById('modalAction');
        const idField = document.getElementById('modalId');

        document.getElementById('nome').value = '';
        document.getElementById('logo').value = '';

        if (mode === 'edit' && data) {
            title.innerText = 'Editar Categoria';
            action.value = 'edit';
            idField.value = data.id;
            document.getElementById('nome').value = data.nome;
            document.getElementById('logo').value = data.logo || '';
        } else {
            title.innerText = 'Nova Categoria';
            action.value = 'add';
        }
        modal.classList.add('active');
    }

    function closeModal() {
        document.getElementById('categoryModal').classList.remove('active');
    }

    function confirmDelete(id, name) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteName').innerText = name;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    
    // Auto-hide messages
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(el => el.style.display = 'none');
    }, 4000);
</script>

<?php require_once 'includes/footer.php'; ?>