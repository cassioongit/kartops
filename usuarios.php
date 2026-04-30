<?php
/**
 * =====================================================
 * GESTÃO DE USUÁRIOS - KartOps
 * =====================================================
 * Página para gerenciar usuários do sistema
 * Acesso: Apenas Owner e Admin
 */

// Configurações da página
$pageTitle = 'Gestão de Usuários';
$additionalCSS = ['/css/usuarios.css'];

// Incluir header
require_once 'includes/header.php';

// Verificar se usuário está logado
if (!$usuario) {
    header('Location: index.php');
    exit;
}

// Verificar se é Owner ou Admin (Colaboradores não têm acesso)
if ($usuario['role'] !== 'Admin' && $usuario['role'] !== 'Owner') {
    header('Location: home.php');
    exit;
}

// Buscar todos os usuários
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.email, u.telefone, u.contato_emergencia_nome, u.contato_emergencia_telefone, 
               u.role, u.ativo, u.id_piloto, u.avatar_url, u.criado_em as created_at, u.atualizado_em as updated_at,
               p.nome as piloto_nome
        FROM usuarios u
        LEFT JOIN pilotos p ON u.id_piloto = p.id
        ORDER BY u.criado_em DESC
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("[KartOps] Erro: " . $e->getMessage());
$error = 'Erro interno do servidor. Tente novamente mais tarde.';
    $usuarios = [];
}
?>

<!-- Gestão de Usuários Content -->
<div style="padding-top: 110px; max-width: 1400px; margin: 0 auto; padding: 110px 20px 40px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="home.php" class="btn-back-home" title="Voltar para Home">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 style="font-size: 32px; margin-bottom: 8px; color: #ffffff;">👥 Gestão de Usuários</h1>
                <p style="color: #e0e0e0; font-size: 14px;">Gerencie todos os usuários do sistema</p>
            </div>
        </div>
        <?php if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner'): ?>
            <button onclick="openAddUserModal()" class="btn-primary">
                ➕ Adicionar Usuário
            </button>
        <?php endif; ?>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Debug: Indicador visual de que o JavaScript está funcionando -->
    <div id="js-status"
        style="display: none; position: fixed; top: 10px; right: 10px; background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px; z-index: 9999; font-size: 12px;">
        ✅ JavaScript carregado
    </div>
    <script>
        // Mostrar indicador quando JS carregar
        setTimeout(function () {
            const statusDiv = document.getElementById('js-status');
            if (statusDiv) {
                statusDiv.style.display = 'block';
                setTimeout(function () {
                    statusDiv.style.display = 'none';
                }, 3000);
            }
        }, 100);
    </script>

    <!-- Filtros e Busca -->
    <div class="filters-container">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 Buscar por nome ou email..." onkeyup="filterUsers()">
        </div>
        <div class="filter-buttons">
            <button class="filter-btn active" data-role="all" onclick="filterByRole('all')">Todos
                (<?= count($usuarios) ?>)</button>
            <button class="filter-btn" data-role="Admin" onclick="filterByRole('Admin')">Admins</button>
            <button class="filter-btn" data-role="Owner" onclick="filterByRole('Owner')">Owner</button>
            <button class="filter-btn" data-role="Colaborador"
                onclick="filterByRole('Colaborador')">Colaboradores</button>
            <button class="filter-btn" data-role="Usuário" onclick="filterByRole('Usuário')">Usuários</button>
        </div>
    </div>

    <!-- Tabela de Usuários -->
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Cadastrado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php foreach ($usuarios as $user): ?>
                    <tr data-role="<?= htmlspecialchars($user['role']) ?>">
                        <td>
                            <div class="user-info">
                                <div class="user-avatar-placeholder" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                                    <i class="fas fa-user" style="font-size: 0.9rem; opacity: 0.5;"></i>
                                </div>
                                <span class="user-name">
                                    <?= htmlspecialchars($user['nome']) ?>
                                    <?php if (!empty($user['id_piloto'])): ?>
                                        <span title="Piloto vinculado: <?= htmlspecialchars($user['piloto_nome']) ?>"
                                            style="cursor: help; margin-left: 5px; font-size: 1.1em;">🏎️</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td style="color: #333;"><?= htmlspecialchars($user['email']) ?></td>
                        <td style="color: #333;">
                            <span class="role-badge role-<?= strtolower(htmlspecialchars($user['role'])) ?>">
                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                        </td>
                        <td style="color: #333;"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="viewUser('<?= $user['id'] ?>')" class="btn-action btn-view"
                                    title="Visualizar">
                                    👁️
                                </button>
                                <?php if ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner'): ?>
                                    <button onclick="editUser('<?= $user['id'] ?>')" class="btn-action btn-edit" title="Editar">
                                        ✏️
                                    </button>
                                <?php endif; ?>

                                <?php if (($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner') && $user['id'] !== $usuario['id']): ?>
                                    <button class="btn-action btn-delete btn-delete-user" title="Excluir"
                                        data-user-id="<?= htmlspecialchars($user['id'], ENT_QUOTES) ?>"
                                        data-user-name="<?= htmlspecialchars($user['nome'], ENT_QUOTES) ?>">
                                        🗑️
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Adicionar/Editar Usuário -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Adicionar Usuário</h2>
            <button onclick="closeUserModal()" class="modal-close">✕</button>
        </div>
        <form id="userForm" onsubmit="saveUser(event)">
            <input type="hidden" id="userId" name="userId">

            <div class="form-group">
                <label for="userName">Nome Completo *</label>
                <input type="text" id="userName" name="nome" required>
            </div>

            <div class="form-group">
                <label for="userEmail">Email *</label>
                <input type="email" id="userEmail" name="email" required>
            </div>



            <div class="form-group">
                <label for="userTelefone">Telefone <span
                        style="color: #999; font-weight: 400;">(opcional)</span></label>
                <input type="tel" id="userTelefone" name="telefone" placeholder="(11) 99999-9999">
            </div>

            <div class="form-group">
                <label for="userEmergenciaNome">Contato de Emergência - Nome <span
                        style="color: #999; font-weight: 400;">(opcional)</span></label>
                <input type="text" id="userEmergenciaNome" name="contato_emergencia_nome"
                    placeholder="Nome do contato de emergência">
            </div>

            <div class="form-group">
                <label for="userEmergenciaTelefone">Contato de Emergência - Telefone <span
                        style="color: #999; font-weight: 400;">(opcional)</span></label>
                <input type="tel" id="userEmergenciaTelefone" name="contato_emergencia_telefone"
                    placeholder="(11) 99999-9999">
            </div>

            <div class="form-group">
                <label for="userRole">Tipo de Conta *</label>
                <select id="userRole" name="role" required>
                    <option value="Usuário">Usuário</option>
                    <option value="Colaborador">Colaborador</option>
                    <option value="Admin">Admin</option>
                    <option value="Owner">Owner</option>
                </select>
            </div>

            <!-- Campos extras para Owner/Admin (só aparecem na edição) -->
            <div class="form-group" id="pilotoIdGroup" style="display: none;">
                <label for="userPilotoId">Vincular a Piloto <span
                        style="color: #999; font-weight: 400;">(opcional)</span></label>
                <select id="userPilotoId" name="id_piloto">
                    <option value="">-- Nenhum piloto vinculado --</option>
                    <?php
                    // Buscar pilotos disponíveis
                    try {
                        $stmtPilotos = $pdo->prepare("SELECT id, nome FROM pilotos ORDER BY nome ASC");
                        $stmtPilotos->execute();
                        $pilotos = $stmtPilotos->fetchAll();
                        foreach ($pilotos as $piloto) {
                            echo '<option value="' . htmlspecialchars($piloto['id']) . '" data-pilot-id="' . htmlspecialchars($piloto['id']) . '">' . htmlspecialchars($piloto['nome']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        // Ignora erro se não conseguir buscar pilotos
                    }
                    ?>
                </select>
                <small style="color: #666;">Vincule este usuário a um piloto cadastrado no sistema.</small>
            </div>

            <?php
            // Coletar IDs de pilotos já vinculados a outros usuários
            $linkedPilots = [];
            foreach ($usuarios as $u) {
                if (!empty($u['id_piloto'])) {
                    $linkedPilots[$u['id_piloto']] = $u['id']; // pilot_id => user_id
                }
            }
            ?>
            <script>
                // Mapa de pilotos vinculados: { piloto_id: user_id }
                const linkedPilots = <?= json_encode($linkedPilots) ?>;

                /**
                 * Filtra o dropdown de pilotos ao editar um usuário.
                 * Esconde pilotos já vinculados a OUTROS usuários.
                 * Mantém visível o piloto vinculado ao usuário atual (se houver).
                 */
                function filterPilotoDropdown(currentUserId) {
                    const select = document.getElementById('userPilotoId');
                    const options = select.querySelectorAll('option[data-pilot-id]');

                    options.forEach(option => {
                        const pilotId = option.getAttribute('data-pilot-id');
                        const linkedToUserId = linkedPilots[pilotId];

                        if (linkedToUserId && linkedToUserId != currentUserId) {
                            // Piloto vinculado a OUTRO usuário — esconder
                            option.style.display = 'none';
                            option.disabled = true;
                        } else {
                            // Piloto disponível ou vinculado ao usuário atual — mostrar
                            option.style.display = '';
                            option.disabled = false;
                        }
                    });
                }
            </script>

            <div class="form-group" id="ativoGroup" style="display: none;">
                <label for="userAtivo">Status da Conta</label>
                <select id="userAtivo" name="ativo">
                    <option value="1">✅ Ativo</option>
                    <option value="0">🚫 Inativo (bloqueado)</option>
                </select>
                <small style="color: #666;">Contas inativas não conseguem fazer login no sistema.</small>
            </div>

            <div class="form-group" id="passwordGroup">
                <label for="userPassword">Senha *</label>
                <input type="password" id="userPassword" name="senha" minlength="8">
                <small style="color: #666; display: block; margin-top: 6px;">
                    <strong>Requisitos:</strong> Mínimo 8 caracteres, 1 maiúscula, 1 minúscula, 1 número
                </small>
            </div>

            <div class="form-group" id="confirmPasswordGroup">
                <label for="userConfirmPassword">Confirmar Senha *</label>
                <input type="password" id="userConfirmPassword" name="confirmar_senha" minlength="8">
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeUserModal()" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Visualizar Usuário -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalhes do Usuário</h2>
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

<!-- Modal: Confirmar Exclusão -->
<div id="deleteConfirmModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>⚠️ Confirmar Exclusão</h2>
            <button onclick="closeDeleteConfirmModal()" class="modal-close">✕</button>
        </div>
        <div style="padding: 20px;">
            <p style="font-size: 16px; margin-bottom: 15px; color: #333;">
                Tem certeza que deseja excluir o usuário <strong id="deleteUserName"></strong>?
            </p>
            <p style="color: #f44336; font-weight: 600; margin-bottom: 20px;">
                ⚠️ Esta ação não pode ser desfeita!
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeDeleteConfirmModal()" class="btn-secondary">Cancelar</button>
            <button id="confirmDeleteBtn" onclick="executeDelete()" class="btn-primary" style="background: #f44336;">
                Sim, Excluir Usuário
            </button>
        </div>
    </div>
</div>

<script>
    // Debug: Verificar se o script está carregando
    console.log('=== SCRIPT usuarios.php CARREGADO ===');
    console.log('Timestamp:', new Date().toISOString());

    // Dados dos usuários (para JavaScript)
    const usersData = <?= json_encode($usuarios) ?>;
    console.log('Usuários carregados:', usersData.length);

    // Filtrar usuários por busca
    function filterUsers() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#usersTableBody tr');

        rows.forEach(row => {
            const name = row.querySelector('.user-name').textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();

            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Filtrar por tipo de conta
    function filterByRole(role) {
        const rows = document.querySelectorAll('#usersTableBody tr');
        const buttons = document.querySelectorAll('.filter-btn');

        // Atualizar botões ativos
        buttons.forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-role="${role}"]`).classList.add('active');

        // Filtrar linhas
        rows.forEach(row => {
            if (role === 'all' || row.dataset.role === role) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Abrir modal de adicionar
    function openAddUserModal() {
        document.getElementById('modalTitle').textContent = 'Adicionar Usuário';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('currentAvatarUrl').value = ''; // Reset hidden field
        document.getElementById('passwordGroup').style.display = 'block';
        document.getElementById('confirmPasswordGroup').style.display = 'block';
        document.getElementById('userPassword').required = true;
        document.getElementById('userConfirmPassword').required = true;
        document.getElementById('userRole').disabled = false;
        // Ocultar campos extras ao adicionar (só aparecem na edição)
        document.getElementById('pilotoIdGroup').style.display = 'none';
        document.getElementById('ativoGroup').style.display = 'none';
        document.getElementById('userModal').style.display = 'flex';
    }

    // Fechar modal de usuário
    function closeUserModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    // Fechar modal de visualização
    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    // Visualizar usuário
    function viewUser(userId) {
        const user = usersData.find(u => u.id == userId);
        if (!user) return;

        const content = `
        <div class="view-user-details">
            <div class="view-avatar">
                <div class="avatar-placeholder-large"><i class="fas fa-user"></i></div>
            </div>
            <div class="view-info">
                <div class="info-row">
                    <strong>Nome:</strong>
                    <span>${user.nome}</span>
                </div>
                <div class="info-row">
                    <strong>Email:</strong>
                    <span>${user.email}</span>
                </div>
                <div class="info-row">
                    <strong>Telefone:</strong>
                    <span>${user.telefone || 'Não informado'}</span>
                </div>
                <div class="info-row" style="border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px;">
                    <strong>Contato de Emergência:</strong>
                    <span>${user.contato_emergencia_nome || 'Não informado'}</span>
                </div>
                <div class="info-row">
                    <strong>Tel. Emergência:</strong>
                    <span>${user.contato_emergencia_telefone || 'Não informado'}</span>
                </div>
                <div class="info-row" style="border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px;">
                    <strong>Tipo de Conta:</strong>
                    <span class="role-badge role-${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span>
                </div>
                <div class="info-row">
                    <strong>Status:</strong>
                    <span style="color: ${user.ativo == 1 ? '#4caf50' : '#f44336'}; font-weight: 600;">
                        ${user.ativo == 1 ? '✅ Ativo' : '🚫 Inativo'}
                    </span>
                </div>
                <div class="info-row">
                    <strong>Piloto Vinculado:</strong>
                    <span>${user.piloto_nome ? '🏎️ ' + user.piloto_nome : '<span style="color:#999">Nenhum piloto vinculado</span>'}</span>
                </div>
                <div class="info-row">
                    <strong>Cadastrado em:</strong>
                    <span>${new Date(user.created_at).toLocaleString('pt-BR')}</span>
                </div>
                <div class="info-row">
                    <strong>Última atualização:</strong>
                    <span>${new Date(user.updated_at).toLocaleString('pt-BR')}</span>
                </div>
            </div>
        </div>
    `;

        document.getElementById('viewModalContent').innerHTML = content;
        document.getElementById('viewModal').style.display = 'flex';
    }

    // Editar usuário
    function editUser(userId) {
        const user = usersData.find(u => u.id == userId);
        if (!user) return;

        const currentUserRole = <?= json_encode($usuario['role']) ?>;

        document.getElementById('modalTitle').textContent = 'Editar Usuário';
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = user.nome;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userTelefone').value = user.telefone || '';
        document.getElementById('userEmergenciaNome').value = user.contato_emergencia_nome || '';
        document.getElementById('userEmergenciaTelefone').value = user.contato_emergencia_telefone || '';
        // Referência a currentAvatarUrl removida pois o campo não existe mais
        document.getElementById('userRole').value = user.role;

        // Campos extras para Owner/Admin: piloto_id e ativo
        if (currentUserRole === 'Admin' || currentUserRole === 'Owner') {
            document.getElementById('pilotoIdGroup').style.display = 'block';
            document.getElementById('ativoGroup').style.display = 'block';
            filterPilotoDropdown(user.id); // Filtrar pilotos já vinculados a outros usuários
            document.getElementById('userPilotoId').value = user.id_piloto || '';
            document.getElementById('userAtivo').value = user.ativo ? '1' : '0';
            document.getElementById('userRole').disabled = false;
        } else {
            document.getElementById('pilotoIdGroup').style.display = 'none';
            document.getElementById('ativoGroup').style.display = 'none';
        }

        // Ocultar campos de senha ao editar
        document.getElementById('passwordGroup').style.display = 'none';
        document.getElementById('confirmPasswordGroup').style.display = 'none';
        document.getElementById('userPassword').required = false;
        document.getElementById('userConfirmPassword').required = false;

        document.getElementById('userModal').style.display = 'flex';
    }

    // Salvar usuário
    async function saveUser(event) {
        event.preventDefault();

        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        const formData = new FormData(form);
        const userId = formData.get('userId');
        const action = userId ? 'update' : 'create';

        // Add action explicitly to FormData
        formData.append('action', action);

        // Handle checkbox/select logic differences if needed (but FormData handles inputs well)
        // Ensure 'ativo' is 0 or 1
        if (!formData.has('ativo')) {
            // If not present (e.g. disabled), and we are editing, we might need to be careful.
            // But our logic uses a select which always sends a value if visible.
        }

        const senha = formData.get('senha');
        const confirmarSenha = formData.get('confirmar_senha');

        // Validação de senha para novos usuários
        if (!userId && senha) {
            if (senha !== confirmarSenha) {
                alert('As senhas não coincidem');
                return;
            }
            if (senha.length < 8) {
                alert('A senha deve ter no mínimo 8 caracteres');
                return;
            }
            if (!/[A-Z]/.test(senha)) {
                alert('A senha deve conter pelo menos uma letra maiúscula');
                return;
            }
            if (!/[a-z]/.test(senha)) {
                alert('A senha deve conter pelo menos uma letra minúscula');
                return;
            }
            if (!/[0-9]/.test(senha)) {
                alert('A senha deve conter pelo menos um número');
                return;
            }
        }

        // Feedback visual e prevenir duplo clique
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        submitBtn.disabled = true;

        try {
            // Note: Do NOT set 'Content-Type': 'multipart/form-data' header manually.
            // The browser sets it automatically with the correct boundary when body is FormData.
            const response = await csrfFetch('api/usuarios.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(userId ? 'Usuário atualizado com sucesso!' : 'Usuário criado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + result.message);
            }
        } finally {
            // Restaurar botão se não houver recarregamento (ex: erro)
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
        }
    }



    // Variáveis globais para controle de exclusão
    let currentDeleteUserId = null;
    let currentDeleteUserName = null;
    let isDeleting = false; // Prevenir múltiplas exclusões simultâneas

    // Abrir modal de confirmação de exclusão
    function openDeleteConfirmModal(userId, userName) {
        if (isDeleting) {
            console.log('Exclusão já em andamento, ignorando novo clique');
            return;
        }

        currentDeleteUserId = userId;
        currentDeleteUserName = userName;
        document.getElementById('deleteUserName').textContent = userName;
        document.getElementById('deleteConfirmModal').style.display = 'flex';

        // Focar no botão de cancelar para facilitar escape
        document.querySelector('#deleteConfirmModal .btn-secondary').focus();
    }

    // Fechar modal de confirmação
    function closeDeleteConfirmModal() {
        document.getElementById('deleteConfirmModal').style.display = 'none';
        currentDeleteUserId = null;
        currentDeleteUserName = null;
    }

    // Executar exclusão após confirmação
    async function executeDelete() {
        if (isDeleting) {
            console.log('Exclusão já em andamento');
            return;
        }

        if (!currentDeleteUserId || !currentDeleteUserName) {
            console.error('Dados de exclusão não encontrados');
            showMessage('Erro: Dados do usuário inválidos', false);
            closeDeleteConfirmModal();
            return;
        }

        isDeleting = true;
        const userId = currentDeleteUserId;
        const userName = currentDeleteUserName;

        // Desabilitar botões durante a exclusão
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const cancelBtn = document.querySelector('#deleteConfirmModal .btn-secondary');
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        confirmBtn.textContent = 'Excluindo...';

        console.log('Executando exclusão do usuário:', { userId, userName });

        try {
            const requestBody = {
                action: 'delete',
                userId: userId
            };

            console.log('Request body:', requestBody);

            const response = await csrfFetch('api/usuarios.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(requestBody)
            });

            console.log('Response status:', response.status);

            const text = await response.text();
            console.log('Response text:', text);

            let result;
            try {
                result = JSON.parse(text);
                console.log('Response parsed:', result);
            } catch (e) {
                console.error('Erro ao parsear resposta:', e);
                console.error('Texto recebido:', text);
                showMessage('Erro: Resposta inválida do servidor. Verifique o console para detalhes.', false);
                isDeleting = false;
                confirmBtn.disabled = false;
                cancelBtn.disabled = false;
                confirmBtn.textContent = 'Sim, Excluir Usuário';
                return;
            }

            if (result.success) {
                console.log('Usuário excluído com sucesso!');
                closeDeleteConfirmModal();
                showMessage('Usuário excluído com sucesso! A página recarregará automaticamente em 5 segundos.', true);
                setTimeout(() => {
                    console.log('Recarregando página...');
                    location.reload();
                }, 5000); // 5 segundos
            } else {
                console.error('Erro ao excluir:', result.message);
                showMessage('Erro: ' + (result.message || 'Não foi possível excluir o usuário'), false);
                isDeleting = false;
                confirmBtn.disabled = false;
                cancelBtn.disabled = false;
                confirmBtn.textContent = 'Sim, Excluir Usuário';
            }
        } catch (error) {
            console.error('Erro ao excluir usuário:', error);
            showMessage('Erro de conexão: ' + error.message, false);
            isDeleting = false;
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            confirmBtn.textContent = 'Sim, Excluir Usuário';
        }
    }

    // Função antiga mantida para compatibilidade (mas não usa mais confirm)
    async function deleteUser(userId, userName) {
        openDeleteConfirmModal(userId, userName);
    }

    // Event listeners para botões de delete (usando data attributes)
    function setupDeleteButtons() {
        console.log('Configurando botões de delete...');
        const deleteButtons = document.querySelectorAll('.btn-delete-user');
        console.log('Botões encontrados:', deleteButtons.length);

        deleteButtons.forEach((button, index) => {
            console.log(`Configurando botão ${index + 1}:`, button);

            // Remover todos os listeners anteriores
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            // Prevenir múltiplos cliques com debounce
            let clickTimeout = null;
            newButton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Limpar timeout anterior se existir
                if (clickTimeout) {
                    clearTimeout(clickTimeout);
                }

                // Adicionar pequeno delay para evitar cliques duplos
                clickTimeout = setTimeout(() => {
                    console.log('CLIQUE NO BOTÃO DE DELETE DETECTADO!');
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    console.log('Dados extraídos:', { userId, userName });

                    if (userId && userName) {
                        deleteUser(userId, userName);
                    } else {
                        console.error('ERRO: Botão de delete sem data attributes:', this);
                        showMessage('Erro: Dados do usuário não encontrados', false);
                    }
                }, 100); // 100ms de debounce
            });

            console.log(`Botão ${index + 1} configurado com sucesso`);
        });

        console.log('Todos os botões de delete foram configurados');
    }

    // Executar quando DOM estiver pronto
    console.log('Estado do DOM:', document.readyState);
    if (document.readyState === 'loading') {
        console.log('Aguardando DOMContentLoaded...');
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOMContentLoaded disparado');
            setupDeleteButtons();
        });
    } else {
        console.log('DOM já está pronto, executando setupDeleteButtons imediatamente');
        setupDeleteButtons();
    }

    // Também tentar após um pequeno delay para garantir
    setTimeout(function () {
        console.log('Verificação adicional após 500ms...');
        const buttons = document.querySelectorAll('.btn-delete-user');
        if (buttons.length > 0) {
            console.log('Botões encontrados na verificação adicional:', buttons.length);
            setupDeleteButtons();
        }
    }, 500);

    // Fechar modais ao clicar fora
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            // Não fechar o modal de confirmação de delete ao clicar fora (só com botão)
            if (event.target.id === 'deleteConfirmModal') {
                return; // Não fechar
            }
            event.target.style.display = 'none';
        }
    }

    // Fechar modal de confirmação com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const deleteModal = document.getElementById('deleteConfirmModal');
            if (deleteModal && deleteModal.style.display === 'flex') {
                closeDeleteConfirmModal();
            }
        }
    });

    // Mostrar mensagem de feedback (5 segundos)
    function showMessage(message, isSuccess = true) {
        // Remover mensagem anterior se existir
        const existingMsg = document.getElementById('feedback-msg');
        if (existingMsg) {
            clearTimeout(existingMsg.timeoutId);
            existingMsg.remove();
        }

        const msgDiv = document.createElement('div');
        msgDiv.id = 'feedback-msg';
        msgDiv.style.cssText = `
            position: fixed;
            top: 120px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: ${isSuccess ? '#4caf50' : '#f44336'};
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            font-weight: 600;
            font-size: 15px;
            max-width: 90%;
            text-align: center;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        `;
        msgDiv.textContent = message;
        document.body.appendChild(msgDiv);

        // Remover após 5 segundos (ou deixar até o reload da página)
        msgDiv.timeoutId = setTimeout(() => {
            if (msgDiv && msgDiv.parentNode) {
                msgDiv.style.opacity = '0';
                setTimeout(() => {
                    if (msgDiv && msgDiv.parentNode) {
                        msgDiv.remove();
                    }
                }, 500);
            }
        }, 5000);
    }
    // Cache buster: 2026-01-29 21:16:00
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>