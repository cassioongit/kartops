<?php
/**
 * =====================================================
 * ONBOARDS - KartOps
 * =====================================================
 * Página para envio e visualização de vídeos onboard
 */

// Configurações da página
$pageTitle = 'Onboards';
$additionalCSS = ['/css/onboards.css'];

// Incluir header
require_once 'includes/header.php';

// Verificar se usuário está logado
if (!$usuario) {
    header('Location: index.php');
    exit;
}


// Verificar se usuário tem piloto vinculado (para não-admins)
$temPilotoVinculado = !empty($usuario['id_piloto']);
$isAdmin = in_array($usuario['role'], ['Admin', 'Owner']);
$canCreateOnboard = $isAdmin || $temPilotoVinculado;
?>

<!-- Onboards Content -->
<div class="onboards-wrapper">

    <!-- Header -->
    <div class="onboards-header">
        <div class="header-left">
            <a href="dashboard.php" class="btn-back" title="Voltar">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1>🎬 Onboards</h1>
        </div>

        <?php if ($canCreateOnboard): ?>
            <button class="btn-add-onboard" onclick="openModal()">
                <span>➕</span>
                <span>Novo Onboard</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Aviso se não tem piloto vinculado (para não-admins e visitantes) -->
    <?php if (!$isAdmin && !$temPilotoVinculado): ?>
        <div class="warning-message">
            <span class="icon">⚠️</span>
            <div class="text">
                <h3>Aviso</h3>
                <p>Para enviar vídeos onboard é necessário estar logado e possuir um piloto vinculado ao seu usuário, em
                    caso de dúvida acione um administrador.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="filters-container">
        <div class="filter-group">
            <label>Filtrar por Etapa</label>
            <select id="filter-etapa" onchange="filterOnboards()">
                <option value="">Todas as etapas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Filtrar por Categoria</label>
            <select id="filter-categoria" onchange="filterOnboards()">
                <option value="">Todas as categorias</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Filtrar por Piloto</label>
            <select id="filter-piloto" onchange="filterOnboards()">
                <option value="">Todos os pilotos</option>
            </select>
        </div>
    </div>

    <!-- Grid de Onboards -->
    <div class="onboards-grid" id="onboards-grid">
        <div class="loading-spinner"></div>
    </div>

</div>

<!-- Modal de Novo Onboard -->
<div class="modal-overlay" id="modal-onboard">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🎬 Novo Onboard</h2>
            <button class="btn-close-modal" onclick="closeModal()">✕</button>
        </div>

        <div class="modal-body">
            <form id="form-onboard" onsubmit="submitOnboard(event)">
                <!-- URL do YouTube -->
                <div class="form-group">
                    <label>URL do YouTube *</label>
                    <input type="url" id="youtube-url" placeholder="https://www.youtube.com/watch?v=..."
                        oninput="validateYoutubeUrl()" required>
                    <p class="help-text">Cole o link do vídeo do YouTube</p>
                </div>

                <!-- Preview do vídeo -->
                <div class="video-preview" id="video-preview">
                    <div class="preview-container">
                        <iframe id="preview-iframe" src="" allowfullscreen></iframe>
                    </div>
                    <div class="preview-title">
                        <strong>Título:</strong> <span id="preview-title">-</span>
                    </div>
                </div>

                <!-- Etapa -->
                <div class="form-group">
                    <label>Etapa *</label>
                    <select id="etapa-select" required>
                        <option value="">Selecione uma etapa...</option>
                    </select>
                    <p class="help-text">Apenas etapas que já ocorreram</p>
                </div>

                <!-- Piloto -->
                <div class="form-group">
                    <label>Piloto *</label>
                    <?php if ($isAdmin): ?>
                        <select id="piloto-select" onchange="updateCategoria()" required>
                            <option value="">Selecione um piloto...</option>
                        </select>
                    <?php else: ?>
                        <input type="hidden" id="piloto-select"
                            value="<?= htmlspecialchars($usuario['id_piloto'] ?? '') ?>">
                        <div class="categoria-display" id="piloto-nome-display">Carregando...</div>
                    <?php endif; ?>
                </div>

                <!-- Categoria (automática) -->
                <div class="form-group">
                    <label>Categoria</label>
                    <div class="categoria-display" id="categoria-display">
                        Será preenchida automaticamente
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-submit" id="btn-submit" disabled>
                    Salvar Onboard
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Variáveis globais
    const isAdmin = <?= json_encode($isAdmin) ?>;
    const usuarioId = <?= json_encode($usuario['id']) ?>;
    const usuarioPilotoId = <?= json_encode($usuario['id_piloto'] ?? null) ?>;

    let allOnboards = [];
    let etapas = [];
    let pilotos = [];
    let categorias = new Map();
    let validVideoData = null;

    // =====================================================
    // INICIALIZAÇÃO
    // =====================================================

    document.addEventListener('DOMContentLoaded', async function () {
        await Promise.all([
            loadOnboards(),
            loadEtapas(),
            loadPilotos()
        ]);
        populateFilters();
    });

    // =====================================================
    // FUNÇÕES DE CARREGAMENTO
    // =====================================================

    async function loadOnboards() {
        try {
            const response = await csrfFetch('api/onboards.php?action=list', {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success) {
                allOnboards = data.data;
                renderOnboards(allOnboards);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao carregar onboards:', error);
            showToast('Erro ao carregar onboards', 'error');
        }
    }

    async function loadEtapas() {
        try {
            const response = await csrfFetch('api/onboards.php?action=etapas', {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success) {
                etapas = data.data;

                // Preencher select de etapas no formulário
                const select = document.getElementById('etapa-select');
                etapas.forEach(etapa => {
                    const option = document.createElement('option');
                    option.value = etapa.id;
                    option.textContent = `${etapa.nome} - ${formatDate(etapa.data)}`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar etapas:', error);
        }
    }

    async function loadPilotos() {
        try {
            const response = await csrfFetch('api/onboards.php?action=pilotos', {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success) {
                pilotos = data.data;

                // Criar mapa de categorias
                pilotos.forEach(p => {
                    if (p.categoria_id) {
                        categorias.set(p.categoria_id, p.categoria_nome);
                    }
                });

                // Se for admin, preencher select de pilotos
                if (isAdmin) {
                    const select = document.getElementById('piloto-select');
                    pilotos.forEach(piloto => {
                        const option = document.createElement('option');
                        option.value = piloto.id;
                        option.textContent = piloto.nome;
                        option.dataset.categoriaId = piloto.categoria_id || '';
                        option.dataset.categoriaNome = piloto.categoria_nome || 'Sem categoria';
                        select.appendChild(option);
                    });
                } else {
                    // Mostrar nome do piloto vinculado
                    const piloto = pilotos.find(p => p.id === usuarioPilotoId);
                    if (piloto) {
                        document.getElementById('piloto-nome-display').textContent = piloto.nome;
                        document.getElementById('categoria-display').textContent = piloto.categoria_nome || 'Sem categoria';
                    }
                }
            }
        } catch (error) {
            console.error('Erro ao carregar pilotos:', error);
        }
    }

    // =====================================================
    // RENDERIZAÇÃO
    // =====================================================

    function renderOnboards(onboards) {
        const grid = document.getElementById('onboards-grid');

        if (onboards.length === 0) {
            grid.innerHTML = `
            <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="icon">🎬</div>
                <h3>Nenhum onboard encontrado</h3>
                <p>Seja o primeiro a compartilhar um vídeo da sua corrida!</p>
            </div>
        `;
            return;
        }

        // Agrupar por etapa_id, ordenando por data decrescente
        const groups = new Map();
        onboards.forEach(o => {
            if (!groups.has(o.etapa_id)) {
                groups.set(o.etapa_id, { nome: o.etapa_nome, data: o.etapa_data, items: [] });
            }
            groups.get(o.etapa_id).items.push(o);
        });

        // Ordenar grupos por data (mais recente primeiro)
        const sorted = [...groups.entries()].sort((a, b) =>
            new Date(b[1].data) - new Date(a[1].data)
        );

        let html = '';
        sorted.forEach(([etapaId, group]) => {
            html += `
            <div class="etapa-section">
                <div class="etapa-section-header">
                    <div class="etapa-section-line"></div>
                    <div class="etapa-section-label">
                        <span class="etapa-section-icon">🏁</span>
                        <span class="etapa-section-nome">${escapeHtml(group.nome)}</span>
                        <span class="etapa-section-data">${formatDate(group.data)}</span>
                    </div>
                    <div class="etapa-section-line"></div>
                </div>
                <div class="onboards-grid-inner">
                    ${group.items.map(onboard => {
                const canDelete = isAdmin || onboard.usuario_id === usuarioId;
                return `
                        <div class="onboard-card" data-id="${onboard.id}"
                             data-etapa="${onboard.etapa_id}"
                             data-categoria="${onboard.categoria_id || ''}"
                             data-piloto="${onboard.piloto_id}">
                            <div class="video-container">
                                <iframe src="https://www.youtube.com/embed/${onboard.youtube_video_id}"
                                        allowfullscreen loading="lazy"></iframe>
                            </div>
                            <div class="onboard-info">
                                <h3 class="onboard-title">${escapeHtml(onboard.titulo || 'Sem título')}</h3>
                                <div class="onboard-meta">
                                    <div class="meta-item piloto-info">
                                        <img src="${onboard.piloto_foto || '/images/turtle-driver.png'}"
                                             class="piloto-foto" alt=""
                                             style="border-color: ${onboard.equipe_cor || 'rgba(255,255,255,0.2)'}"
                                             onerror="this.src='/images/turtle-driver.png'">
                                        <div style="display: flex; flex-direction: column;">
                                            <span style="font-weight: 600; color: #e0e0e0;">${escapeHtml(onboard.piloto_nome)}</span>
                                            ${onboard.equipe_nome ? `<span style="font-size: 0.75rem; color: #a0a0a0;">${escapeHtml(onboard.equipe_nome)}</span>` : ''}
                                        </div>
                                    </div>
                                    ${onboard.categoria_nome ? `
                                        <div class="meta-item">
                                            <span class="badge badge-categoria">${escapeHtml(onboard.categoria_nome)}</span>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            ${canDelete ? `
                                <div class="onboard-actions">
                                    <button class="btn-delete-onboard" onclick="deleteOnboard('${onboard.id}')">
                                        🗑️ Excluir
                                    </button>
                                </div>
                            ` : ''}
                        </div>`;
            }).join('')}
                </div>
            </div>`;
        });

        grid.innerHTML = html;
    }

    function populateFilters() {
        // Etapas
        const filterEtapa = document.getElementById('filter-etapa');
        const uniqueEtapas = [...new Map(allOnboards.map(o => [o.etapa_id, { id: o.etapa_id, nome: o.etapa_nome }])).values()];
        uniqueEtapas.forEach(e => {
            const option = document.createElement('option');
            option.value = e.id;
            option.textContent = e.nome;
            filterEtapa.appendChild(option);
        });

        // Categorias
        const filterCategoria = document.getElementById('filter-categoria');
        const uniqueCategorias = [...new Map(allOnboards.filter(o => o.categoria_id).map(o => [o.categoria_id, { id: o.categoria_id, nome: o.categoria_nome }])).values()];
        uniqueCategorias.forEach(c => {
            const option = document.createElement('option');
            option.value = c.id;
            option.textContent = c.nome;
            filterCategoria.appendChild(option);
        });

        // Pilotos
        const filterPiloto = document.getElementById('filter-piloto');
        const uniquePilotos = [...new Map(allOnboards.map(o => [o.piloto_id, { id: o.piloto_id, nome: o.piloto_nome }])).values()];
        uniquePilotos.forEach(p => {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.nome;
            filterPiloto.appendChild(option);
        });
    }

    function filterOnboards() {
        const etapaId = document.getElementById('filter-etapa').value;
        const categoriaId = document.getElementById('filter-categoria').value;
        const pilotoId = document.getElementById('filter-piloto').value;

        let filtered = allOnboards;

        if (etapaId) {
            filtered = filtered.filter(o => o.etapa_id === etapaId);
        }
        if (categoriaId) {
            filtered = filtered.filter(o => o.categoria_id === categoriaId);
        }
        if (pilotoId) {
            filtered = filtered.filter(o => o.piloto_id === pilotoId);
        }

        renderOnboards(filtered);
    }

    // =====================================================
    // MODAL E FORMULÁRIO
    // =====================================================

    function openModal() {
        document.getElementById('modal-onboard').style.display = 'flex';
        resetForm();
    }

    function closeModal() {
        document.getElementById('modal-onboard').style.display = 'none';
        resetForm();
    }

    function resetForm() {
        document.getElementById('form-onboard').reset();
        document.getElementById('video-preview').classList.remove('active');
        document.getElementById('btn-submit').disabled = true;
        validVideoData = null;

        if (isAdmin) {
            document.getElementById('categoria-display').textContent = 'Será preenchida automaticamente';
        }
    }

    let validateTimeout = null;

    async function validateYoutubeUrl() {
        const url = document.getElementById('youtube-url').value.trim();
        const preview = document.getElementById('video-preview');
        const submitBtn = document.getElementById('btn-submit');

        // Debounce
        if (validateTimeout) {
            clearTimeout(validateTimeout);
        }

        if (!url) {
            preview.classList.remove('active');
            submitBtn.disabled = true;
            validVideoData = null;
            return;
        }

        validateTimeout = setTimeout(async () => {
            try {
                const response = await csrfFetch('api/onboards.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'validate_url', url: url })
                });

                const data = await response.json();

                if (data.success) {
                    validVideoData = data;

                    // Mostrar preview
                    document.getElementById('preview-iframe').src = data.embed_url;
                    document.getElementById('preview-title').textContent = data.titulo || 'Sem título';
                    preview.classList.add('active');

                    // Habilitar submit se etapa e piloto estão selecionados
                    checkFormValidity();
                } else {
                    preview.classList.remove('active');
                    submitBtn.disabled = true;
                    validVideoData = null;
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro ao validar URL:', error);
            }
        }, 500);
    }

    function updateCategoria() {
        const select = document.getElementById('piloto-select');
        const option = select.options[select.selectedIndex];
        const categoriaDisplay = document.getElementById('categoria-display');

        if (option && option.value) {
            categoriaDisplay.textContent = option.dataset.categoriaNome || 'Sem categoria';
        } else {
            categoriaDisplay.textContent = 'Será preenchida automaticamente';
        }

        checkFormValidity();
    }

    function checkFormValidity() {
        const submitBtn = document.getElementById('btn-submit');
        const etapa = document.getElementById('etapa-select').value;
        const piloto = document.getElementById('piloto-select').value;

        submitBtn.disabled = !(validVideoData && etapa && piloto);
    }

    // Adicionar eventos para verificar validade
    document.getElementById('etapa-select').addEventListener('change', checkFormValidity);
    if (document.getElementById('piloto-select').tagName === 'SELECT') {
        document.getElementById('piloto-select').addEventListener('change', checkFormValidity);
    }

    async function submitOnboard(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Salvando...';
        submitBtn.disabled = true;

        try {
            const response = await csrfFetch('api/onboards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'create',
                    youtube_url: document.getElementById('youtube-url').value.trim(),
                    etapa_id: document.getElementById('etapa-select').value,
                    piloto_id: document.getElementById('piloto-select').value
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                closeModal();
                await loadOnboards();
                populateFilters();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao salvar onboard:', error);
            showToast('Erro ao salvar onboard', 'error');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    }

    async function deleteOnboard(id) {
        if (!confirm('Tem certeza que deseja excluir este onboard?')) {
            return;
        }

        try {
            const response = await csrfFetch('api/onboards.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                await loadOnboards();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao excluir onboard:', error);
            showToast('Erro ao excluir onboard', 'error');
        }
    }

    // =====================================================
    // UTILITÁRIOS
    // =====================================================

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        // Remover toast anterior
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Fechar modal ao clicar fora
    document.getElementById('modal-onboard').addEventListener('click', function (e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>