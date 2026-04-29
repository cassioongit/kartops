// Configurar locale para português
const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

// Nota: As variáveis globais 'etapasData' e 'canEdit' são definidas no arquivo PHP antes deste script.

// Filtros ativos
let activeKartodromo = 'all';
let activeTipo = 'all';
let activeStatus = 'upcoming'; // 'upcoming' ou 'past'

// Filtrar etapas por busca
function filterEtapas() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.etapa-card');

    cards.forEach(card => {
        const nome = card.dataset.nome;
        const kartodromo = card.dataset.kartodromo.toLowerCase();
        const patrocinador = card.dataset.patrocinador;
        const tipo = card.dataset.tipo;
        const isPast = card.classList.contains('past') || card.classList.contains('today');

        const matchSearch = nome.includes(searchTerm) ||
            kartodromo.includes(searchTerm) ||
            patrocinador.includes(searchTerm);
        const matchKartodromo = activeKartodromo === 'all' || card.dataset.kartodromo === activeKartodromo;
        const matchTipo = activeTipo === 'all' || tipo.includes(activeTipo);
        const matchStatus = activeStatus === 'past' ? isPast : !isPast;

        if (matchSearch && matchKartodromo && matchTipo && matchStatus) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    updateEmptyState();
}

// Filtrar por kartódromo
function filterByKartodromo(kartodromo) {
    activeKartodromo = kartodromo;

    // Atualizar botões ativos
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${kartodromo}"]`).classList.add('active');

    filterEtapas();
}

// Filtrar por tipo
function filterByTipo(tipo) {
    activeTipo = tipo;

    // Atualizar botões ativos
    document.querySelectorAll('.filter-btn-tipo').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-tipo="${tipo}"]`).classList.add('active');

    filterEtapas();
}

// Filtrar por status (upcoming / past)
function filterByStatus(status) {
    activeStatus = status;

    document.getElementById('btn-upcoming').classList.toggle('active', status === 'upcoming');
    document.getElementById('btn-past').classList.toggle('active', status === 'past');

    filterEtapas();
}

// Verificar estado vazio
function updateEmptyState() {
    const container = document.getElementById('etapasContainer');
    const visibleCards = container.querySelectorAll('.etapa-card:not([style*="display: none"])');
    let emptyState = container.querySelector('.empty-state-filtered');

    if (visibleCards.length === 0) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state empty-state-filtered';
            emptyState.innerHTML = `
                <div class="empty-icon">🔍</div>
                <h3>Nenhuma etapa encontrada</h3>
                <p>Tente ajustar os filtros de busca</p>
            `;
            container.appendChild(emptyState);
        }
    } else if (emptyState) {
        emptyState.remove();
    }
}

// Toggle campo customizado de kartódromo
function toggleKartodromoCustom() {
    const select = document.getElementById('etapaKartodromo');
    const custom = document.getElementById('etapaKartodromoCustom');
    if (select.value === '__outro__') {
        custom.style.display = 'block';
        custom.required = true;
        custom.focus();
    } else {
        custom.style.display = 'none';
        custom.required = false;
        custom.value = '';
    }
}

// Abrir modal de adicionar
function openAddEtapaModal() {
    document.getElementById('modalTitle').textContent = 'Adicionar Etapa';
    document.getElementById('etapaForm').reset();
    document.getElementById('etapaId').value = '';
    document.getElementById('etapaKartodromoCustom').style.display = 'none';
    document.getElementById('etapaKartodromoCustom').required = false;
    document.getElementById('etapaModal').style.display = 'flex';
}

// Fechar modal de etapa
function closeEtapaModal() {
    document.getElementById('etapaModal').style.display = 'none';
}

// Fechar modal de visualização
function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// Formatar data para exibição
function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return `${diasSemana[date.getDay()]}, ${date.getDate()} de ${meses[date.getMonth()]} de ${date.getFullYear()}`;
}

// Visualizar etapa
function viewEtapa(etapaId) {
    const etapa = etapasData.find(e => e.id === etapaId);
    if (!etapa) return;

    const content = `
        <div class="view-etapa-details">
            <div class="view-header-card ${getTipoClassJS(etapa.tipo_etapa)}">
                <span class="view-tipo">${etapa.tipo_etapa}</span>
            </div>
            <div class="view-info">
                <div class="info-row">
                    <strong>Nome:</strong>
                    <span>${etapa.nome}</span>
                </div>
                <div class="info-row">
                    <strong>Data:</strong>
                    <span>${formatDate(etapa.data)}</span>
                </div>
                <div class="info-row">
                    <strong>Horário:</strong>
                    <span>${etapa.hora.substring(0, 5)}</span>
                </div>
                <div class="info-row">
                    <strong>Kartódromo:</strong>
                    <span>${etapa.kartodromo}</span>
                </div>
                <div class="info-row">
                    <strong>Patrocinador:</strong>
                    <span>${etapa.patrocinador || '-'}</span>
                </div>

                <div class="info-row">
                    <strong>Data variável:</strong>
                    <span>${etapa.data_variavel == 1 ? '✅ Sim' : '❌ Não'}</span>
                </div>
                <div class="info-row">
                    <strong>Local variável:</strong>
                    <span>${etapa.local_variavel == 1 ? '✅ Sim' : '❌ Não'}</span>
                </div>
                <div class="info-row">
                    <strong>Criado em:</strong>
                    <span>${new Date(etapa.criado_em).toLocaleString('pt-BR')}</span>
                </div>
            </div>
        </div>
    `;

    document.getElementById('viewModalContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'flex';
}

// Obter classe do tipo (JavaScript)
function getTipoClassJS(tipo) {
    tipo = tipo.toLowerCase();
    if (tipo.includes('regular')) return 'type-regular';
    if (tipo.includes('superpole')) return 'type-superpole';
    if (tipo.includes('grid') || tipo.includes('invertido')) return 'type-grid-invertido';
    if (tipo.includes('endurance')) return 'type-endurance';
    return 'type-tbd';
}

// Editar etapa
function editEtapa(etapaId) {
    const etapa = etapasData.find(e => e.id === etapaId);
    if (!etapa) return;

    document.getElementById('modalTitle').textContent = 'Editar Etapa';
    document.getElementById('etapaId').value = etapa.id;
    document.getElementById('etapaNome').value = etapa.nome;
    document.getElementById('etapaPatrocinador').value = etapa.patrocinador || '';
    document.getElementById('etapaData').value = etapa.data;
    document.getElementById('etapaHora').value = etapa.hora.substring(0, 5);
    document.getElementById('etapaTipo').value = etapa.tipo_etapa;
    document.getElementById('etapaDataVariavel').checked = etapa.data_variavel == 1;
    document.getElementById('etapaLocalVariavel').checked = etapa.local_variavel == 1;

    // Kartódromo: verificar se o valor existe no select, senão usar "Outro"
    const selectKart = document.getElementById('etapaKartodromo');
    const customKart = document.getElementById('etapaKartodromoCustom');
    const optionExists = Array.from(selectKart.options).some(opt => opt.value === etapa.kartodromo);
    if (optionExists) {
        selectKart.value = etapa.kartodromo;
        customKart.style.display = 'none';
        customKart.required = false;
        customKart.value = '';
    } else {
        selectKart.value = '__outro__';
        customKart.style.display = 'block';
        customKart.required = true;
        customKart.value = etapa.kartodromo;
    }

    document.getElementById('etapaModal').style.display = 'flex';
}

// Salvar etapa
async function saveEtapa(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const etapaId = formData.get('etapaId');
    const action = etapaId ? 'update' : 'create';

    // Resolver kartódromo: se "Outro" foi selecionado, usar o campo customizado
    let kartodromo = formData.get('kartodromo');
    if (kartodromo === '__outro__') {
        kartodromo = document.getElementById('etapaKartodromoCustom').value.trim();
        if (!kartodromo) {
            alert('Por favor, digite o nome do kartódromo.');
            return;
        }
    }

    try {
        const response = await csrfFetch('api/etapas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                etapaId: etapaId,
                nome: formData.get('nome'),
                data: formData.get('data'),
                hora: formData.get('hora'),
                kartodromo: kartodromo,
                patrocinador: formData.get('patrocinador'),
                tipo_etapa: formData.get('tipo_etapa'),
                data_variavel: formData.get('data_variavel') ? 1 : 0,
                local_variavel: formData.get('local_variavel') ? 1 : 0
            })
        });

        const result = await response.json();

        if (result.success) {
            // Armazenar dados para notificação por email
            sessionStorage.setItem('etapa_notificacao', JSON.stringify({
                action: action,
                etapa: {
                    nome: formData.get('nome'),
                    data: formData.get('data'),
                    hora: formData.get('hora'),
                    kartodromo: kartodromo,
                    tipo_etapa: formData.get('tipo_etapa')
                }
            }));
            window.location.href = 'enviar_notificacoes.php';
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        alert('Erro ao salvar etapa: ' + error.message);
    }
}

// Excluir etapa
async function deleteEtapa(etapaId, etapaNome) {
    if (!confirm(`Tem certeza que deseja excluir a etapa "${etapaNome}"?\n\nEsta ação não pode ser desfeita.`)) {
        return;
    }

    try {
        const response = await csrfFetch('api/etapas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                etapaId: etapaId
            })
        });

        const result = await response.json();

        if (result.success) {
            // Armazenar dados para notificação por email
            sessionStorage.setItem('etapa_notificacao', JSON.stringify({
                action: 'delete',
                etapa: {
                    nome: etapaNome
                }
            }));
            window.location.href = 'enviar_notificacoes.php';
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (error) {
        alert('Erro ao excluir etapa: ' + error.message);
    }
}

// Fechar modais ao clicar fora
window.onclick = function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Init: aplicar filtro de status na carga
document.addEventListener('DOMContentLoaded', () => {
    filterByStatus('upcoming');
});
