<?php
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="css/classificacao.css?v=<?= time() ?>">

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="classification-header">
            <div class="header-left-group">
                <a href="home.php" class="btn-back-home" title="Voltar para Home">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h1 class="page-title">🏆 Classificação</h1>
                    <p class="text-muted">Acompanhe a pontuação e ranking do campeonato.</p>
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

            <!-- Controls -->
            <div class="controls-wrapper">
            <!-- View Selector (Pilotos/Equipes) -->
            <div class="view-toggle">
                <button class="toggle-btn active" data-view="pilotos">
                    <i class="fas fa-user-helmet"></i> Pilotos
                </button>
                <button class="toggle-btn" data-view="equipes">
                    <i class="fas fa-users-crown"></i> Equipes
                </button>
            </div>

            <!-- Category Selector (Master/Challengers) -->
            <div id="cat-filter-container" class="category-toggle" style="margin-top: 10px;">
                <button class="toggle-btn cat-master active" data-cat="Master">
                    <i class="fas fa-star"></i> Master
                </button>
                <button class="toggle-btn cat-challengers" data-cat="Challengers">
                    <i class="fas fa-trophy"></i> Challengers
                </button>
            </div>
        </div>
        </div>

        <!-- COMPACT VIEW (F1 Style - Mobile Portrait) -->
        <div id="ranking-compact" class="ranking-compact-container">
            <div class="ranking-list" id="compact-list">
                <div class="loading-placeholder">
                    <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                    Carregando classificação...
                </div>
            </div>
        </div>

        <!-- TABLE VIEW (Desktop / Landscape) -->
        <div id="ranking-table" class="classification-table-wrapper">
            <div class="table-responsive">
                <table class="table" id="classificationTable">
                    <thead></thead>
                    <tbody>
                        <tr>
                            <td class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x"></i><br>
                                Carregando classificação...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let currentView = 'pilotos';
    let currentCategory = 'Challengers';

    document.addEventListener('DOMContentLoaded', () => {
        loadFilters();
    });

    function setView(view) {
        currentView = view;
        document.querySelectorAll('.view-toggle .toggle-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        loadClassification();
    }

    function setCategory(catName) {
        currentCategory = catName;
        document.querySelectorAll('.category-toggle .toggle-btn').forEach(btn => {
            const isSelected = btn.dataset.cat === catName;
            btn.classList.toggle('active', isSelected);
        });
        loadClassification();
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.toggle-btn');
        if (!btn) return;
        
        if (btn.dataset.view) {
            setView(btn.dataset.view);
        } else if (btn.dataset.cat) {
            setCategory(btn.dataset.cat);
        }
    });


    async function loadFilters() {
        try {
            const res = await csrfFetch('api/categorias.php');
            const json = await res.json();
            const container = document.getElementById('cat-filter-container');
            container.innerHTML = '';

            if (json.data) {
                let hasChallengers = false;
                json.data.forEach(c => {
                    const name = c.nome;
                    const isMaster = name === 'Master';
                    const isChallenger = name.includes('Challenger') || name.includes('Challenge');
                    if (!isMaster && !isChallenger) return;

                    const btn = document.createElement('button');
                    btn.className = 'toggle-btn';
                    if (isMaster) btn.classList.add('cat-master');
                    if (isChallenger) btn.classList.add('cat-challengers');
                    btn.dataset.cat = name;
                    
                    const icon = isMaster ? '<i class="fas fa-star"></i>' : '<i class="fas fa-trophy"></i>';
                    btn.innerHTML = `${icon} ${name}`;
                    container.appendChild(btn);


                    if (isChallenger) hasChallengers = true;
                });

                if (hasChallengers) {
                    currentCategory = 'Challengers';
                } else {
                    const firstBtn = container.querySelector('button');
                    if (firstBtn) currentCategory = firstBtn.dataset.cat;
                }
                setCategory(currentCategory);
            }
        } catch (e) {
            console.error(e);
        }
        loadClassification();
    }

    async function loadClassification() {
        const categoria = currentCategory;

        // Compact View Elements
        const compactList = document.getElementById('compact-list');
        compactList.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Calculando pontos...</div>';

        // Table View Elements
        const table = document.getElementById('classificationTable');
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="100" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Calculando pontos...</td></tr>';

        try {
            let url = `api/classificacao.php?tipo=${currentView}`;
            if (categoria) url += `&categoria=${encodeURIComponent(categoria)}`;

            const res = await csrfFetch(url);
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            const etapas = json.etapas;

            // ========== RENDER COMPACT VIEW (F1 Style) ==========
            compactList.innerHTML = '';
            if (json.data.length === 0) {
                compactList.innerHTML = '<div class="no-data">Nenhum registro encontrado.</div>';
            } else {
                json.data.forEach((row, index) => {
                    const teamColor = (currentView === 'pilotos') ? (row.equipe_cor || '#1c1e32') : (row.cor || '#1c1e32');

                    // Converter cor hex para rgba com transparência
                    const hexToRgba = (hex, alpha) => {
                        const r = parseInt(hex.slice(1, 3), 16);
                        const g = parseInt(hex.slice(3, 5), 16);
                        const b = parseInt(hex.slice(5, 7), 16);
                        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                    };
                    const bgColor = hexToRgba(teamColor, 0.75); // 75% de opacidade

                    let totalDisplay = parseFloat(row.total).toFixed(1);
                    if (totalDisplay.endsWith('.0')) totalDisplay = parseInt(row.total);

                    // Badge de posição
                    const position = index + 1;
                    let posClass = 'pos-default';
                    if (position === 1) posClass = 'pos-gold';
                    else if (position === 2) posClass = 'pos-silver';
                    else if (position === 3) posClass = 'pos-bronze';

                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'ranking-row';
                    rowDiv.style.backgroundColor = bgColor;

                    if (currentView === 'pilotos') {
                        // Layout para pilotos: posição + logo equipe + nome + foto + pontos
                        const logoSrc = row.equipe_img || '/images/logo-campeonato.png';
                        const photoSrc = row.foto || '/images/turtle-driver.png';
                        rowDiv.innerHTML = `
                            <div class="pos-badge ${posClass}">${position}º</div>
                            <img src="${logoSrc}" class="team-logo-mini" alt="" onerror="this.src='/images/logo-campeonato.png'">
                            <span class="driver-name">${row.nome}</span>
                            <img src="${photoSrc}" class="driver-photo" alt="" onerror="this.src='/images/turtle-driver.png'">
                            <div class="points-box">${totalDisplay}</div>
                        `;
                    } else {
                        // Layout para equipes: posição + logo grande centralizado + pontos
                        const logoSrc = row.foto || '/images/logo-campeonato.png';
                        rowDiv.classList.add('ranking-row-team');
                        rowDiv.innerHTML = `
                            <div class="pos-badge ${posClass}">${position}º</div>
                            <img src="${logoSrc}" class="team-logo-full" alt="${row.nome}" onerror="this.src='/images/logo-campeonato.png'">
                            <div class="points-box">${totalDisplay}</div>
                        `;
                    }
                    compactList.appendChild(rowDiv);
                });
            }

            // ========== RENDER TABLE VIEW (Desktop) ==========
            let headerHtml = `
                <tr>
                    <th width="1%" class="compact-col">Pos</th>
                    <th width="1%" class="compact-col">${currentView === 'pilotos' ? 'Piloto' : 'Equipe'}</th>
                    ${currentView === 'pilotos' ? '<th width="1%" class="compact-col">Equipe</th>' : ''}
            `;
            etapas.forEach((et, index) => {
                headerHtml += `<th width="45" style="text-align: center;">ET${index + 1}</th>`;
            });
            headerHtml += `<th width="65" style="text-align: center;">Total</th></tr>`;
            thead.innerHTML = headerHtml;

            tbody.innerHTML = '';
            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${3 + etapas.length}" class="text-center py-4">Nenhum registro encontrado.</td></tr>`;
                return;
            }

            json.data.forEach(row => {
                const tr = document.createElement('tr');
                const teamColor = (currentView === 'pilotos') ? (row.equipe_cor || '#1c1e32') : (row.cor || '#1c1e32');

                // Converter cor hex para rgba com transparência (mesmo padrão da view vertical)
                const hexToRgba = (hex, alpha) => {
                    const r = parseInt(hex.slice(1, 3), 16);
                    const g = parseInt(hex.slice(3, 5), 16);
                    const b = parseInt(hex.slice(5, 7), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                };
                const bgColor = hexToRgba(teamColor, 0.75); // 75% de opacidade
                tr.style.background = `linear-gradient(90deg, ${bgColor} 0%, transparent 100%)`;

                let posClass = row.pos <= 3 ? `pos-${row.pos}` : '';
                let posBadge = `<div class="pos-circle ${posClass}">${row.pos}º</div>`;
                let entityNameHtml = '';
                let teamHtml = '';

                if (currentView === 'pilotos') {
                    const logoSrc = row.equipe_img || '/images/logo-campeonato.png';
                    const photoSrc = row.foto || '/images/turtle-driver.png';
                    
                    entityNameHtml = `
                        <div class="entity-cell">
                            <img src="${photoSrc}" class="entity-avatar" onerror="this.src='/images/turtle-driver.png'">
                            <div class="entity-info">
                                <span class="entity-name">${row.nome} <span style="font-size:0.75em">${'🏆'.repeat(row.vitorias || 0)}</span></span>
                            </div>
                        </div>
                    `;

                    teamHtml = `
                        <div class="entity-cell">
                            <div class="entity-info">
                                <span class="entity-sub">${row.equipe || '-'}</span>
                            </div>
                        </div>
                    `;
                } else {
                    const logoSrc = row.foto || '/images/logo-campeonato.png';
                    entityNameHtml = `
                        <div class="entity-cell">
                            <img src="${logoSrc}" class="team-logo-table" style="height: 40px; width: auto; max-width: 140px;" onerror="this.src='/images/logo-campeonato.png'">
                        </div>
                    `;
                }

                let stagesHtml = '';
                etapas.forEach(et => {
                    const pts = row.pontos_por_etapa[et.id];
                    let valDisplay = '-';
                    let zeroClass = '';
                    if (pts !== undefined && pts !== null) {
                        valDisplay = parseFloat(pts).toFixed(1);
                        if (valDisplay.endsWith('.0')) valDisplay = parseInt(pts);
                        if (pts == 0) zeroClass = 'zero';
                    }
                    stagesHtml += `<td class="stage-pt ${zeroClass}" style="text-align: center !important;">${valDisplay}</td>`;
                });

                let totalDisplay = parseFloat(row.total).toFixed(1);
                if (totalDisplay.endsWith('.0')) totalDisplay = parseInt(row.total);

                tr.innerHTML = `
                    <td class="compact-col">${posBadge}</td>
                    <td class="compact-col">${entityNameHtml}</td>
                    ${currentView === 'pilotos' ? `<td class="compact-col">${teamHtml}</td>` : ''}
                    ${stagesHtml}
                    <td style="text-align: center;"><div class="total-points">${totalDisplay}</div></td>
                `;
                tbody.appendChild(tr);
            });

        } catch (e) {
            compactList.innerHTML = `<div class="no-data" style="color:red;">Erro: ${e.message}</div>`;
            tbody.innerHTML = `<tr><td colspan="100" class="text-center text-danger">Erro: ${e.message}</td></tr>`;
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>