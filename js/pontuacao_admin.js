function filterTable(category, btn) {
    document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('.row-item');
    rows.forEach(row => {
        if (category === 'all' || row.dataset.category === category) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openModal() {
    document.getElementById('modalForm').style.display = 'flex';
    document.getElementById('pontuacaoForm').reset();
    document.getElementById('p_id').value = '';
    document.getElementById('formAction').value = 'create';
    document.getElementById('modalTitle').innerText = 'Nova Regra';
}

function closeModal() {
    document.getElementById('modalForm').style.display = 'none';
}

function editItem(data) {
    document.getElementById('modalForm').style.display = 'flex';
    document.getElementById('modalTitle').innerText = 'Editar Regra';
    document.getElementById('formAction').value = 'update';

    document.getElementById('p_id').value = data.id;
    document.getElementById('p_categoria').value = data.categoria;
    document.getElementById('p_peso').value = data.peso || 1;
    document.getElementById('p_posicao').value = data.posicao;
    document.getElementById('p_sem1').value = data.primeiro_semestre;
    document.getElementById('p_jul').value = data.julho;
    document.getElementById('p_ago').value = data.agosto;
    document.getElementById('p_set').value = data.setembro;
    document.getElementById('p_out').value = data.outubro;
    document.getElementById('p_nov').value = data.novembro;
    document.getElementById('p_dez').value = data.dezembro;
}

async function saveItem(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Converter form data para tipos corretos se necessário ou enviar como JSON
    const payload = {
        action: document.getElementById('formAction').value,
        id: data.id,
        categoria: data.categoria,
        peso: parseInt(data.peso) || 1,
        posicao: parseInt(data.posicao),
        primeiro_semestre: parseInt(data.primeiro_semestre),
        julho: parseInt(data.julho),
        agosto: parseInt(data.agosto),
        setembro: parseInt(data.setembro),
        outubro: parseInt(data.outubro),
        novembro: parseInt(data.novembro),
        dezembro: parseInt(data.dezembro)
    };

    try {
        const resp = await csrfFetch('api/pontuacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await resp.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (err) {
        alert('Erro de conexão');
    }
}

async function deleteItem(id) {
    if (!confirm('Tem certeza?')) return;

    try {
        const resp = await csrfFetch('api/pontuacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const result = await resp.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.message);
        }
    } catch (err) {
        alert('Erro de conexão');
    }
}

// Fechar modal ao clicar fora
window.onclick = function (event) {
    const modal = document.getElementById('modalForm');
    if (event.target == modal) {
        closeModal();
    }
}
