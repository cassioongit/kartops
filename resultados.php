<?php
require_once 'includes/header.php';

// Controle de Acesso (usa $usuario do auth_session.php incluído pelo header.php)
$canEdit = $usuario && in_array($usuario['role'], ['Admin', 'Owner', 'Colaborador']);
$isGuest = !$usuario || isset($_SESSION['is_guest']);
?>

<?php if ($isGuest): ?>
    <div
        style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:60vh; text-align:center; padding:40px 20px;">
        <div style="font-size:3rem; margin-bottom:20px;">🔒</div>
        <h2 style="color:#f0f0f0; margin-bottom:12px;">Acesso Restrito</h2>
        <p style="color:#90959f; font-size:1rem; max-width:420px; line-height:1.6;">
            Para visualizar os resultados é necessário estar logado.
        </p>
        <div style="display:flex; gap:16px; margin-top:24px;">
            <a href="home.php"
                style="padding:12px 28px; background:transparent; color:#90959f; border: 1px solid rgba(255,255,255,0.1); border-radius:12px; text-decoration:none; font-weight:600; font-size:0.95rem; transition: background 0.2s;">
                Voltar
            </a>
            <a href="index.php"
                style="padding:12px 28px; background:#6366f1; color:#fff; border-radius:12px; text-decoration:none; font-weight:600; font-size:0.95rem;">
                🔑 Fazer Login
            </a>
        </div>
    </div>
    <?php require_once 'includes/footer.php'; ?>
    <?php exit; ?>
<?php endif; ?>

<link rel="stylesheet" href="css/resultados.css">

<div class="main-content">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="results-header">
            <div class="header-left-group" style="display:flex; align-items:center; gap:16px;">
                <a href="home.php" class="btn-back-home" title="Voltar para Home">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="page-title">📊 Resultados</h1>
                </div>
            </div>

            <!-- Rotate Phone Hint (Mobile Portrait Only) -->
            <div class="rotate-hint">
                <div class="phone-icon">
                    <div class="phone-body"></div>
                    <div class="rotate-arrows">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 12a8 8 0 0 1 14.5-4.5M20 12a8 8 0 0 1-14.5 4.5" stroke-linecap="round" />
                            <path d="M18.5 7.5l-0.5-3.5-3.5 0.5M5.5 16.5l0.5 3.5 3.5-0.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
                <span>Gire o celular para ver mais detalhes</span>
            </div>

            <div class="d-flex flex-column gap-3" style="width: 100%; max-width: 400px;">
                <div>
                    <select id="filterEtapa" class="form-control" onchange="loadResults()" style="width: 100%;">
                        <option value="">Todas as Etapas</option>
                        <!-- Populated via JS -->
                    </select>
                </div>

                <div>
                    <select id="filterCategoria" class="form-control" onchange="loadResults()" style="width: 100%;">
                        <option value="" disabled selected>Selecione a Categoria</option>
                        <!-- Populated via JS -->
                    </select>
                </div>

                <div>
                    <select id="filterPiloto" class="form-control" onchange="loadResults()" style="width: 100%;">
                        <option value="">Todos os Pilotos</option>
                        <!-- Populated via JS -->
                    </select>
                </div>

                <?php if ($canEdit): ?>
                    <button class="btn btn-primary" onclick="openResultModal()" style="width: 100%; margin-bottom: 8px;">
                        <i class="fas fa-plus"></i> Novo Resultado
                    </button>
                    <button id="btnLancarFaltas" class="btn btn-danger" onclick="lancarZerados()" style="width: 100%; background-color: #dc3545; color: white; border: none;">
                        <i class="fas fa-user-times"></i> Lançar Faltas
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabela de Registros -->
        <div class="results-table-container">
            <p style="color: #888; font-size: 0.9rem; margin-bottom: 1rem; text-align: center;">
                💡 Para melhor visualização, utilize um PC ou gire seu celular na horizontal.
            </p>
            <div class="table-responsive">
                <table class="table table-hover" id="resultsTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="50">Pos</th>
                            <th class="text-center" width="65">Foto</th>
                            <th width="150">Piloto</th>
                            <th width="130">Equipe</th>
                            <th width="100">Categoria</th>
                            <th width="160">Etapa</th>
                            <th width="130">Local</th>
                            <th class="text-center" width="70">Pontos</th>
                            <th width="110" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                                Carregando registros...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Resultado -->
<div id="resultModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Lançar Resultado</h2>
            <span class="close" onclick="closeModal('resultModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="resultForm" onsubmit="saveResult(event)">
                <input type="hidden" id="resultId">

                <div class="form-group">
                    <label>Etapa</label>
                    <select id="modalEtapaSelect" class="form-control" required onchange="filterPilotsByCategory()">
                        <option value="">Selecione a Etapa...</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Categoria (Obrigatório)</label>
                            <select id="categoriaSelect" class="form-control" required
                                onchange="handleModalCategoryChange()">
                                <option value="">Selecione a Categoria...</option>
                                <!-- Populated via JS -->
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Posição</label>
                            <input type="number" id="posicaoInput" class="form-control" min="0" required
                                placeholder="Ex: 1">
                            <small class="text-muted">Use 0 para Falta (0 pts).</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Piloto</label>
                    <select id="pilotoSelect" class="form-control" required disabled>
                        <option value="">Selecione primeiro a categoria...</option>
                    </select>
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn btn-info" id="btnSaveAdd" onclick="saveResult(event, true)">Salvar
                        e adicionar</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Penalidades -->
<div id="penalidadesModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2>⚠️ Penalidades</h2>
            <span class="close" onclick="closeModal('penalidadesModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div id="penalidadesContent">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                    Carregando...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
    let nextSuggestedPos = 1; // Sugestão automática de posição
    let allEtapasMap = {}; // Mapa para guardar info das etapas (tipo, etc)
    let lastModalCategory = ''; // Guardar última categoria para evitar reset de posição indesejado

    document.addEventListener('DOMContentLoaded', () => {
        loadEtapas(); // Carrega para o Filtro e para o Modal
        loadData(); // Carrega categorias e pilotos para filtros (todos usuários precisam)
        loadResults(); // Carrega tudo inicialmente

        // Escutador para o comando "Reset pls" (via console ou digitação cega se preferir, mas console é mais seguro)
        // Para facilitar, vamos escutar por uma sequência de teclas
        let buffer = "";
        document.addEventListener('keydown', (e) => {
            buffer += e.key;
            if (buffer.endsWith("Reset pls")) {
                buffer = "";
                resetTestData();
            }
            if (buffer.length > 20) buffer = buffer.substring(10);
        });
    });

    async function resetTestData() {
        if (!confirm('Deseja realmente apagar TODOS os dados da "Etapa de Teste Lógica" (Reset pls)?')) return;
        try {
            const response = await csrfFetch('api/resultados.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset_test_stage' })
            });
            const json = await response.json();
            alert(json.message);
            if (json.success) {
                location.reload(); // Recarregar tudo
            }
        } catch (e) {
            alert('Erro ao resetar: ' + e.message);
        }
    }

    async function loadEtapas() {
        try {
            const response = await csrfFetch('api/etapas.php?action=list');
            const json = await response.json();
            const etapas = json.etapas || json;

            // 1. Preencher Filtro
            const filterSelect = document.getElementById('filterEtapa');

            // 2. Preencher Modal Select (se existir, pois user pode não ser admin)
            const modalSelect = document.getElementById('modalEtapaSelect');

            const today = new Date();
            today.setHours(23, 59, 59, 999);

            etapas.forEach(etapa => {
                // Guardar info da etapa para consulta posterior
                allEtapasMap[etapa.id] = etapa;

                const etapaDate = new Date(etapa.data + 'T00:00:00');
                // Mostrar apenas etapas realizadas (passadas ou hoje)
                if (etapaDate > today) return;

                const data = etapaDate.toLocaleDateString('pt-BR');
                const label = `${etapa.nome} (${data})`;

                // Filtro
                const opt1 = document.createElement('option');
                opt1.value = etapa.id;
                opt1.textContent = label;
                filterSelect.appendChild(opt1);

                // Modal
                if (modalSelect) {
                    const opt2 = document.createElement('option');
                    opt2.value = etapa.id;
                    opt2.textContent = label;
                    modalSelect.appendChild(opt2);
                }
            });

        } catch (error) {
            console.error('Erro ao carregar etapas:', error);
        }
    }

    let allPilotos = [];

    async function loadData() {
        // Load Categorias DETALHADAS (Master, Challengers, Challenger II, Challengers III)
        // Usamos ?detailed=true para resultados, pois cada corrida tem pontuação diferente
        try {
            const resCat = await csrfFetch('api/categorias.php?detailed=true');
            const dataCat = await resCat.json();
            const categorias = dataCat.data || [];

            const catSelect = document.getElementById('categoriaSelect');
            const filterCatSelect = document.getElementById('filterCategoria');

            categorias.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.nome; // Usando Nome pois o DB salva Nome
                opt.dataset.id = c.id;
                opt.textContent = c.nome;
                catSelect.appendChild(opt);

                if (filterCatSelect) {
                    const optF = document.createElement('option');
                    optF.value = c.nome;
                    optF.textContent = c.nome;
                    filterCatSelect.appendChild(optF);
                }
            });
        } catch (e) { console.error('Erro cats', e); }

        // Load Pilotos (Store locally for filtering)
        try {
            const resPil = await csrfFetch('api/pilotos.php?simple=true');
            const dataPil = await resPil.json();
            allPilotos = dataPil.data || dataPil;

            // Populate Filter
            const filterPilotoSelect = document.getElementById('filterPiloto');
            if (filterPilotoSelect) {
                // Sort by name
                const sorted = [...allPilotos].sort((a, b) => a.nome.localeCompare(b.nome));
                sorted.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nome;
                    filterPilotoSelect.appendChild(opt);
                });
            }

        } catch (e) { console.error('Erro pilots', e); }
    }

    let currentResults = []; // Store currently loaded results

    async function filterPilotsByCategory() {
        const catSelect = document.getElementById('categoriaSelect');
        const selectedCatName = catSelect.value;
        const pilotoSelect = document.getElementById('pilotoSelect');
        const modalEtapaId = document.getElementById('modalEtapaSelect').value;
        const currentEditingId = document.getElementById('resultId').value;

        pilotoSelect.innerHTML = '<option value="">Selecione o Piloto...</option>';

        if (!selectedCatName) {
            pilotoSelect.disabled = true;
            return;
        }

        // 1. Filter by Category
        let filtered = [];

        // Regra: Master mostra apenas pilotos com categoria = "Master"
        // Challengers, Challengers II, Challengers III mostram apenas pilotos com categoria = "Challengers"
        if (selectedCatName === 'Master') {
            // Match exato para Master
            filtered = allPilotos.filter(p => p.categoria_nome === 'Master');
        } else if (selectedCatName.includes('Challenger') || selectedCatName.includes('Challenge')) {
            // Para qualquer variação de Challenger, mostrar apenas pilotos com categoria exata "Challengers"
            filtered = allPilotos.filter(p => p.categoria_nome === 'Challengers');
        } else {
            // Para outras categorias, match exato
            filtered = allPilotos.filter(p => p.categoria_nome === selectedCatName);
        }

        // 2. Filter out pilots who already have a result in THIS stage
        if (modalEtapaId) {
            // Buscar resultados da etapa selecionada diretamente da API para garantir precisão
            let pilotsWithResults = new Set();

            try {
                const response = await csrfFetch(`api/resultados.php?action=list&etapa_id=${modalEtapaId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    // Criar Set com IDs dos pilotos que já têm resultado nesta etapa
                    result.data.forEach(r => {
                        pilotsWithResults.add(r.piloto_id);
                    });
                }
            } catch (error) {
                console.error('Erro ao buscar resultados da etapa:', error);
                // Fallback: usar currentResults se disponível
                currentResults.forEach(r => {
                    if (r.etapa_id == modalEtapaId) {
                        pilotsWithResults.add(r.piloto_id);
                    }
                });
            }

            // Filtrar pilotos que já têm resultado, exceto se estivermos editando esse resultado
            filtered = filtered.filter(p => {
                const hasResult = pilotsWithResults.has(p.id);

                if (!hasResult) {
                    return true; // Não tem resultado, permitir
                }

                // Se tem resultado, só permitir se estivermos editando esse resultado específico
                if (currentEditingId) {
                    // Buscar o resultado existente deste piloto nesta etapa
                    const existingResult = currentResults.find(r =>
                        r.etapa_id == modalEtapaId &&
                        r.piloto_id == p.id &&
                        r.id == currentEditingId
                    );
                    if (existingResult) {
                        return true; // É o resultado que estamos editando, permitir
                    }
                }

                return false; // Tem resultado e não estamos editando, excluir
            });
        }

        // 3. Preencher select
        if (filtered.length === 0) {
            pilotoSelect.innerHTML = '<option value="">Todos os pilotos desta categoria já pontuaram nesta etapa.</option>';
            pilotoSelect.disabled = true;
        } else {
            pilotoSelect.disabled = false;
            // Ordenar por nome
            filtered.sort((a, b) => a.nome.localeCompare(b.nome));
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nome;
                pilotoSelect.appendChild(opt);
            });
        }
    }

    async function loadResults() {
        const etapaId = document.getElementById('filterEtapa').value;
        const categoria = document.getElementById('filterCategoria') ? document.getElementById('filterCategoria').value : '';
        const pilotoId = document.getElementById('filterPiloto') ? document.getElementById('filterPiloto').value : '';
        const tbody = document.querySelector('#resultsTable tbody');

        // FORCE CATEGORY SELECTION
        if (!categoria) {
            tbody.innerHTML = `
                <tr>
                   <td colspan="10" class="text-center py-5">
                       <i class="fas fa-filter fa-3x mb-3 text-muted"></i><br>
                       Selecione uma Categoria acima para visualizar os resultados.
                   </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Atualizando registros...</td></tr>';

        let url = 'api/resultados.php?action=list';
        if (etapaId) url += `&etapa_id=${etapaId}`;
        if (categoria) url += `&categoria=${encodeURIComponent(categoria)}`;
        if (pilotoId) url += `&piloto_id=${encodeURIComponent(pilotoId)}`;

        try {
            const response = await csrfFetch(url);
            const result = await response.json();

            if (!result.success) throw new Error(result.message);

            const data = result.data;
            currentResults = data; // Update global store

            // Re-run filter if modal is open to update availability immediately?
            // Only if modal is visible.
            if (document.getElementById('resultModal').style.display === 'block') {
                filterPilotsByCategory();
            }

            tbody.innerHTML = '';
            // ... (rest of rendering)

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">Nenhum registro encontrado.</td></tr>';
                return;
            }

            data.forEach(row => {
                const tr = document.createElement('tr');

                // Format Data
                const dataEtapa = row.etapa_data ? new Date(row.etapa_data).toLocaleDateString('pt-BR') : '-';

                // Badge Pos
                let posBadge = `<span class="pos-badge pos-${row.posicao}" style="width:24px;height:24px;font-size:0.8rem;">${row.posicao}º</span>`;
                if (row.posicao == 0) posBadge = `<span class="badge badge-secondary" style="font-size:0.8rem;">NC</span>`;

                tr.innerHTML = `
                <td class="text-center">${posBadge}</td>
                <td class="text-center">
                    <img src="${row.piloto_foto || 'images/turtle-driver.png'}" 
                         class="pilot-avatar-small" 
                         alt="Avatar"
                         onerror="this.src='/images/turtle-driver.png'"
                         style="border: 3px solid ${row.equipe_cor || 'transparent'}; padding: 2px;">
                </td>
                <td>
                    <span style="font-weight: 500;">${row.piloto_nome}</span>
                </td>
                <td>
                    <span style="font-weight: 500; color: ${row.equipe_cor || 'inherit'};">${row.equipe_nome || '-'}</span>
                </td>
                <td><span class="badge badge-outline">${row.categoria}</span></td>
                <td>
                    <div>${row.etapa_nome || '-'}</div>
                    <small class="text-muted">${dataEtapa}</small>
                </td>
                <td><small class="text-muted">${row.etapa_kartodromo || '-'}</small></td>
                <td class="text-center">
                    ${row.desclassificado ? `
                        <strong style="color:#ff4757;">0.0</strong>
                        <br><span class="badge badge-secondary" style="background:#ff4757; font-size:0.7rem;">DESCLASSIFICADO</span>
                    ` : `
                        <strong style="color:var(--primary-color)">${parseFloat(row.pontos_final || row.pontos || 0).toFixed(1)}</strong>
                        ${row.pontua_para_equipe == 1 ? '<span class="pontua-equipe-badge" title="Pontua para equipe">+</span>' : ''}
                        ${row.pontos_penalidade && Math.abs(parseFloat(row.pontos_penalidade)) > 0 ? `
                            <br><small style="color:#ff4757;">(${parseFloat(row.pontos || 0).toFixed(1)} ${parseFloat(row.pontos_penalidade) < 0 ? '-' : '+'} ${Math.abs(parseFloat(row.pontos_penalidade)).toFixed(1)})</small>
                        ` : ''}
                    `}
                </td>
                <td class="text-center">
                    ${CAN_EDIT ? `
                        <button class="btn-icon" onclick='editResult(${JSON.stringify(row)})' title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" onclick='openPenalidadesModal("${row.id}", "${row.piloto_id}", "${row.etapa_id}")' title="Penalidades" style="background:#ff6b6b;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteResult('${row.id}')" title="Excluir"><i class="fas fa-trash"></i></button>
                    ` : `
                        ${(Math.abs(parseFloat(row.pontos_penalidade_val || row.pontos_penalidade || 0)) > 0 || row.desclassificado == 1) ? `
                            <button class="btn-icon" onclick='openPenalidadesModal("${row.id}", "${row.piloto_id}", "${row.etapa_id}")' title="Ver Penalidades" style="background:#ff6b6b; opacity: 0.8;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        ` : '<span class="text-muted">-</span>'}
                    `}
                </td>
            `;
                tbody.appendChild(tr);
            });

        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-danger">Erro: ${error.message}</td></tr>`;
        }
    }



    function openResultModal() {
        document.getElementById('resultModal').style.display = 'block';
        document.getElementById('resultForm').reset();
        document.getElementById('resultId').value = '';
        document.getElementById('pilotoSelect').disabled = true;
        document.getElementById('modalTitle').textContent = 'Lançar Resultado';
        
        // Resetar contador de posição ao iniciar novo lançamento
        nextSuggestedPos = 1;
        document.getElementById('posicaoInput').value = nextSuggestedPos;

        // Show Save & Add button for new entries
        const btnAdd = document.getElementById('btnSaveAdd');
        if (btnAdd) btnAdd.style.display = 'inline-block';

        // Se filtro estiver selecionado, pré-selecionar no modal
        const currentFilter = document.getElementById('filterEtapa').value;
        if (currentFilter) {
            document.getElementById('modalEtapaSelect').value = currentFilter;
        }

        // Pre-select Category filter if active
        const currentCatFilter = document.getElementById('filterCategoria') ? document.getElementById('filterCategoria').value : '';
        if (currentCatFilter) {
            document.getElementById('categoriaSelect').value = currentCatFilter;
            filterPilotsByCategory();
        }
    }

    function handleModalCategoryChange() {
        // Resetar posição sugerida apenas quando mudar a categoria manualmente no modal
        // e se não estivermos editando um registro existente
        if (!document.getElementById('resultId').value) {
            nextSuggestedPos = 1;
            document.getElementById('posicaoInput').value = nextSuggestedPos;
        }
        filterPilotsByCategory();
    }

    function editResult(row) {
        openResultModal();
        document.getElementById('modalTitle').textContent = 'Editar Resultado';
        document.getElementById('resultId').value = row.id;
        document.getElementById('modalEtapaSelect').value = row.etapa_id;

        // Hide Save & Add for edits
        const btnAdd = document.getElementById('btnSaveAdd');
        if (btnAdd) btnAdd.style.display = 'none';

        // 1. Set Category
        const catSelect = document.getElementById('categoriaSelect');
        catSelect.value = row.categoria;

        // 2. Refresh Pilots based on Category
        filterPilotsByCategory();

        // 3. Set Pilot (Wait a tick to ensure filter happened if async, but it's sync now)
        setTimeout(() => {
            document.getElementById('pilotoSelect').value = row.piloto_id;
        }, 50);

        document.getElementById('posicaoInput').value = row.posicao;
    }

    async function saveResult(e, keepOpen = false) {
        if (e) e.preventDefault();

        const btn = document.getElementById('btnSave');
        const btnAdd = document.getElementById('btnSaveAdd');

        btn.disabled = true;
        if (btnAdd) btnAdd.disabled = true;

        const oldText = btn.textContent;
        btn.textContent = 'Salvando...';

        const payload = {
            action: 'save',
            id: document.getElementById('resultId').value,
            etapa_id: document.getElementById('modalEtapaSelect').value, // Pega do Modal Agora
            piloto_id: document.getElementById('pilotoSelect').value,
            posicao: document.getElementById('posicaoInput').value,
            categoria: document.getElementById('categoriaSelect').value
        };

        try {
            const res = await csrfFetch('api/resultados.php', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            const json = await res.json();

            if (json.success) {
                // Refresh table
                await loadResults();

                if (keepOpen) {
                    // Incrementar sugestão baseada no que acabou de ser salvo
                    // REGRA: Apenas incrementa se NÃO for ENDURANCE
                    const etapaId = document.getElementById('modalEtapaSelect').value;
                    const infoEtapa = allEtapasMap[etapaId];
                    const isEndurance = infoEtapa && infoEtapa.tipo_etapa === 'Endurance';

                    const savedPos = parseInt(document.getElementById('posicaoInput').value) || 0;
                    if (savedPos > 0 && !isEndurance) {
                        nextSuggestedPos = savedPos + 1;
                    } else if (isEndurance) {
                        nextSuggestedPos = savedPos; // Mantém a mesma posição para Endurance
                    }

                    // Reset fields for next entry
                    document.getElementById('pilotoSelect').value = "";
                    document.getElementById('posicaoInput').value = nextSuggestedPos;
                    document.getElementById('resultId').value = ""; // Ensure creating new

                    // Re-filter pilots to update availability list (remove just added pilot)
                    filterPilotsByCategory();

                    // Optional: Visual feedback
                    const originalText = btnAdd.textContent;
                    btnAdd.textContent = "Salvo! Próximo...";
                    setTimeout(() => {
                        if (btnAdd) btnAdd.textContent = originalText;
                    }, 1500);

                } else {
                    closeModal('resultModal');
                }
            } else {
                alert('Erro: ' + json.message);
            }
        } catch (err) {
            alert('Erro de conexão');
        } finally {
            btn.disabled = false;
            if (btnAdd) btnAdd.disabled = false;
            btn.textContent = oldText;
        }
    }

    async function deleteResult(id) {
        if (!confirm('Tem certeza que deseja excluir?')) return;
        try {
            const res = await csrfFetch('api/resultados.php', {
                method: 'POST',
                body: JSON.stringify({ action: 'delete', id: id })
            });
            const json = await res.json();
            if (json.success) loadResults();
            else alert(json.message);
        } catch (e) { alert('Erro ao excluir'); }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Fechar modal ao clicar fora
    window.onclick = function (event) {
        const modals = ['resultModal', 'penalidadesModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (event.target == modal) {
                modal.style.display = "none";
            }
        });
    }

    // =====================================================
    // FUNÇÕES DE PENALIDADES
    // =====================================================
    let currentResultadoId = null;
    let currentPilotoId = null;
    let currentEtapaId = null;
    let tiposPenalidade = [];

    async function openPenalidadesModal(resultadoId, pilotoId, etapaId) {
        currentResultadoId = resultadoId;
        currentPilotoId = pilotoId;
        currentEtapaId = etapaId;

        document.getElementById('penalidadesModal').style.display = 'block';
        await loadPenalidades();
    }

    async function loadTiposPenalidade() {
        if (tiposPenalidade.length > 0) return tiposPenalidade;

        try {
            const response = await csrfFetch('api/penalidades.php?action=tipos');
            const result = await response.json();
            if (result.success) {
                tiposPenalidade = result.data;
            }
        } catch (error) {
            console.error('Erro ao carregar tipos de penalidade:', error);
        }
        return tiposPenalidade;
    }

    async function loadPenalidades() {
        const content = document.getElementById('penalidadesContent');
        content.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Carregando...</div>';

        try {
            const response = await csrfFetch(`api/penalidades.php?action=list&resultado_id=${currentResultadoId}`);
            const result = await response.json();

            if (!result.success) throw new Error(result.message);

            const penalidades = result.data || [];
            await loadTiposPenalidade();

            let html = ``;

            if (CAN_EDIT) {
                html += `
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-primary" onclick="openAddPenalidadeModal()">
                        <i class="fas fa-plus"></i> Adicionar Penalidade
                    </button>
                </div>
                `;
            }

            if (penalidades.length === 0) {
                html += '<p style="text-align: center; color: #888; padding: 20px;">Nenhuma penalidade aplicada.</p>';
            } else {
                html += '<div class="table-responsive"><table class="table" style="margin-top: 10px;">';
                html += '<thead><tr><th>Tipo</th><th>Pontos</th><th>Tempo +</th><th>Observações</th>';
                if (CAN_EDIT) html += '<th>Ações</th>';
                html += '</tr></thead><tbody>';

                penalidades.forEach(pen => {
                    const pontos = parseFloat(pen.pontos_penalidade || 0);
                    const tempo = parseInt(pen.tempo_adicional_segundos || 0);
                    html += `
                        <tr>
                            <td><strong>${pen.tipo_nome}</strong></td>
                            <td><span style="color: #ff4757;">${pontos.toFixed(1)} pts</span></td>
                            <td>${tempo > 0 ? `+${tempo}s` : '-'}</td>
                            <td><small>${pen.observacoes || '-'}</small></td>
                            ${CAN_EDIT ? `
                            <td>
                                <button class="btn-icon" onclick='editPenalidade("${pen.id}")' title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete" onclick='deletePenalidade("${pen.id}")' title="Remover">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            ` : ''}
                        </tr>
                    `;
                });

                const totalPenalidades = penalidades.reduce((sum, p) => sum + parseFloat(p.pontos_penalidade || 0), 0);
                html += `
                    <tr style="background: rgba(255,71,87,0.1); font-weight: bold;">
                        <td colspan="4" style="text-align: right;">Total de Penalidades:</td>
                        <td><span style="color: #ff4757;">-${totalPenalidades.toFixed(1)} pts</span></td>
                    </tr>
                `;
                html += '</tbody></table></div>';
            }

            content.innerHTML = html;
        } catch (error) {
            content.innerHTML = `<div class="text-danger">Erro ao carregar penalidades: ${error.message}</div>`;
        }
    }

    function openAddPenalidadeModal() {
        const tipos = tiposPenalidade.map(tp => `
            <option value="${tp.id}" data-pontos="${tp.pontos_padrao}" data-tempo="${tp.adiciona_tempo_segundos || 0}">
                ${tp.nome} (${tp.pontos_padrao} pts${tp.adiciona_tempo_segundos > 0 ? `, +${tp.adiciona_tempo_segundos}s` : ''})
            </option>
        `).join('');

        const html = `
            <h3 style="margin-bottom: 15px;">Adicionar Penalidade</h3>
            <form id="penalidadeForm" onsubmit="savePenalidade(event)">
                <div class="form-group">
                    <label>Tipo de Penalidade *</label>
                    <select id="tipoPenalidadeSelect" class="form-control" required onchange="updatePontosPenalidade()">
                        <option value="">Selecione...</option>
                        ${tipos}
                    </select>
                </div>
                <div class="form-group">
                    <label>Pontos (editável)</label>
                    <input type="number" id="pontosPenalidadeInput" class="form-control" step="0.1" required>
                    <small class="text-muted">Valor padrão será preenchido automaticamente</small>
                </div>
                <div class="form-group">
                    <label>Observações</label>
                    <textarea id="observacoesPenalidade" class="form-control" rows="3" placeholder="Detalhes adicionais..."></textarea>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn btn-secondary" onclick="closeAddPenalidadeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        `;

        document.getElementById('penalidadesContent').innerHTML = html;
    }

    function updatePontosPenalidade() {
        const select = document.getElementById('tipoPenalidadeSelect');
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('pontosPenalidadeInput').value = option.dataset.pontos || 0;
        }
    }

    function closeAddPenalidadeModal() {
        loadPenalidades();
    }

    async function savePenalidade(e) {
        if (e) e.preventDefault();

        const tipoId = document.getElementById('tipoPenalidadeSelect').value;
        const pontos = parseFloat(document.getElementById('pontosPenalidadeInput').value);
        const observacoes = document.getElementById('observacoesPenalidade').value;

        try {
            const response = await csrfFetch('api/penalidades.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    resultado_id: currentResultadoId,
                    tipo_penalidade_id: tipoId,
                    pontos_custom: pontos,
                    observacoes: observacoes
                })
            });

            const result = await response.json();
            if (result.success) {
                await loadPenalidades();
                await loadResults(); // Recarregar tabela de resultados
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao salvar penalidade: ' + error.message);
        }
    }

    async function deletePenalidade(id) {
        if (!confirm('Tem certeza que deseja remover esta penalidade?')) return;

        try {
            const response = await csrfFetch('api/penalidades.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            });

            const result = await response.json();
            if (result.success) {
                await loadPenalidades();
                await loadResults();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao remover penalidade: ' + error.message);
        }
    }

    function editPenalidade(id) {
        // Obter a penalidade da lista carregada localmente (poderíamos salvar em variável global ou buscar via DOM, mas podemos refazer a requisição local)
        // Por simplicidade, faremos um fetch para pegar a penalidade específica se precisarmos, mas loadPenalidades já chamou a api que retornou a lista.
        // Já que a API tem uma action list que aceita o ID da penalidade não diretamente, vamos fazer uma gambiarra ou refazer a listagem e achar:
        csrfFetch(`api/penalidades.php?action=list&resultado_id=${currentResultadoId}`)
            .then(res => res.json())
            .then(result => {
                const pen = result.data.find(p => p.id === id);
                if (!pen) return alert('Penalidade não encontrada');

                const tipos = tiposPenalidade.map(tp => `
                    <option value="${tp.id}" data-pontos="${tp.pontos_padrao}" data-tempo="${tp.adiciona_tempo_segundos || 0}" ${tp.id === pen.tipo_penalidade_id ? 'selected' : ''}>
                        ${tp.nome} (${tp.pontos_padrao} pts${tp.adiciona_tempo_segundos > 0 ? `, +${tp.adiciona_tempo_segundos}s` : ''})
                    </option>
                `).join('');

                const html = `
                    <h3 style="margin-bottom: 15px;">Editar Penalidade</h3>
                    <form id="penalidadeEditForm" onsubmit="saveEditPenalidade(event, '${pen.id}')">
                        <div class="form-group">
                            <label>Tipo de Penalidade *</label>
                            <!-- Diferente do back, o tipo de penalidade nao eh atualizado na API update, so pontos e observacoes. Deixaremos desativado -->
                            <select id="tipoPenalidadeSelectEdit" class="form-control" disabled>
                                ${tipos}
                            </select>
                            <small class="text-muted">O tipo de penalidade não pode ser alterado. Exclua e crie uma nova se necessário.</small>
                        </div>
                        <div class="form-group">
                            <label>Pontos (editável)</label>
                            <input type="number" id="pontosPenalidadeInputEdit" class="form-control" step="0.1" required value="${parseFloat(pen.pontos_penalidade)}">
                        </div>
                        <div class="form-group">
                            <label>Observações</label>
                            <textarea id="observacoesPenalidadeEdit" class="form-control" rows="3">${pen.observacoes || ''}</textarea>
                        </div>
                        <div class="modal-footer-custom">
                            <button type="button" class="btn btn-secondary" onclick="closeAddPenalidadeModal()">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                `;
                document.getElementById('penalidadesContent').innerHTML = html;
            });
    }

    async function saveEditPenalidade(e, id) {
        if (e) e.preventDefault();

        const pontos = parseFloat(document.getElementById('pontosPenalidadeInputEdit').value);
        const observacoes = document.getElementById('observacoesPenalidadeEdit').value;

        try {
            const response = await csrfFetch('api/penalidades.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    id: id,
                    pontos_custom: pontos,
                    observacoes: observacoes
                })
            });

            const result = await response.json();
            if (result.success) {
                await loadPenalidades();
                await loadResults(); // Recarregar tabela de resultados principal
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            alert('Erro ao editar penalidade: ' + error.message);
        }
    }

    async function lancarZerados() {
        const etapaId = document.getElementById('filterEtapa').value;
        const categoriaFiltro = document.getElementById('filterCategoria').value;
        
        if (!etapaId) {
            alert('Por favor, selecione uma etapa específica no filtro acima antes de lançar faltas.');
            return;
        }

        const msgConfirm = categoriaFiltro 
            ? `Deseja realmente lançar faltas (posição 0) para todos os pilotos da categoria "${categoriaFiltro}" que ainda não possuem resultado nesta etapa?`
            : 'Deseja realmente lançar faltas (posição 0) para TODOS os pilotos de TODAS as categorias que ainda não possuem resultado nesta etapa?';

        if (!confirm(msgConfirm)) {
            return;
        }

        const btn = document.getElementById('btnLancarFaltas');
        const originalContent = btn.innerHTML;
        
        try {
            // Feedback Visual: Desabilitar e mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

            const response = await csrfFetch('api/resultados.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'lancar_zerados',
                    etapa_id: etapaId,
                    categoria: categoriaFiltro
                })
            });

            const json = await response.json();
            if (json.success) {
                alert(json.message || 'Faltas lançadas com sucesso!');
                location.reload(); // Recarregar página para atualizar tudo
            } else {
                alert('Erro: ' + json.message);
            }
        } catch (error) {
            alert('Erro ao lançar faltas: ' + error.message);
        } finally {
            // Restaurar botão
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    function manualRefresh() {
        // Simplesmente chama loadResults() para atualizar os dados mantendo os filtros atuais do DOM
        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
        
        loadResults().finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>