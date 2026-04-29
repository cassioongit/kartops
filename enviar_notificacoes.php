<?php
/**
 * Página de Progresso — Envio de Notificações de Etapa
 * Recebe dados via sessionStorage (JavaScript) e envia emails um a um
 */
$pageTitle = 'Enviando Notificações';
$additionalCSS = ['/css/etapas.css'];
require_once 'includes/header.php';

// Verificar permissão
$role = $usuario['role'] ?? '';
if (!in_array($role, ['Admin', 'Owner'])) {
    header('Location: etapas.php');
    exit;
}
?>

<main class="main-content" style="max-width: 700px; margin: 120px auto 40px; padding: 0 20px;">
    <div
        style="background: rgba(30,30,40,0.85); border-radius: 16px; padding: 40px; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 8px 32px rgba(0,0,0,0.3);">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-size: 48px; margin-bottom: 10px;" id="headerEmoji">📧</div>
            <h2 style="color: #fff; margin: 0; font-size: 22px;" id="headerTitle">Enviando Notificações...</h2>
            <p style="color: #888; margin: 8px 0 0; font-size: 14px;" id="headerSubtitle">Aguarde enquanto os emails são
                enviados</p>
        </div>

        <!-- Barra de Progresso -->
        <div
            style="background: rgba(255,255,255,0.05); border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 20px;">
            <div id="progressBar"
                style="background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; width: 0%; border-radius: 10px; transition: width 0.3s ease;">
            </div>
        </div>
        <div style="text-align: center; margin-bottom: 24px;">
            <span id="progressText" style="color: #aaa; font-size: 14px; font-weight: 600;">0 / 0</span>
        </div>

        <!-- Log de Envios -->
        <div id="sendLog"
            style="max-height: 400px; overflow-y: auto; font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 13px; background: rgba(0,0,0,0.3); border-radius: 10px; padding: 16px; border: 1px solid rgba(255,255,255,0.05);">
        </div>

        <!-- Botão Voltar (escondido até finalizar) -->
        <div id="doneSection" style="display: none; text-align: center; margin-top: 24px;">
            <a href="etapas.php"
                style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 15px rgba(102,126,234,0.4); transition: transform 0.2s;"
                onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                🏁 Voltar para Etapas
            </a>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', async function () {
        // Ler dados do sessionStorage
        const notifData = sessionStorage.getItem('etapa_notificacao');
        if (!notifData) {
            document.getElementById('headerEmoji').textContent = '⚠️';
            document.getElementById('headerTitle').textContent = 'Nenhuma notificação pendente';
            document.getElementById('headerSubtitle').textContent = 'Redirecionando para etapas...';
            setTimeout(() => window.location.href = 'etapas.php', 2000);
            return;
        }

        const data = JSON.parse(notifData);
        sessionStorage.removeItem('etapa_notificacao'); // Limpar após ler

        const tipoAcao = data.action; // create, update, delete
        const etapaData = data.etapa;

        // Buscar lista de usuários
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        let users = [];
        try {
            const res = await fetch('api/notificacoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'list_users' })
            });
            const result = await res.json();
            if (!result.success) throw new Error(result.message);
            users = result.data;
        } catch (err) {
            addLog('❌ Erro ao buscar usuários: ' + err.message, 'error');
            showDone(0, 0);
            return;
        }

        const total = users.length;
        document.getElementById('progressText').textContent = `0 / ${total}`;

        if (total === 0) {
            addLog('⚠️ Nenhum usuário encontrado para notificar', 'warn');
            showDone(0, 0);
            return;
        }

        // Enviar um a um
        let sent = 0;
        let failed = 0;

        for (let i = 0; i < total; i++) {
            const user = users[i];
            const current = i + 1;

            try {
                const res = await fetch('api/notificacoes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        action: 'send_single',
                        tipo_acao: tipoAcao,
                        etapa_data: etapaData,
                        email: user.email,
                        nome: user.nome
                    })
                });
                const result = await res.json();

                if (result.success) {
                    sent++;
                    addLog(`✅ ${current}/${total} — ${user.nome} (${user.email})`, 'success');
                } else {
                    failed++;
                    addLog(`❌ ${current}/${total} — ${user.nome} (${user.email}) — ${result.message}`, 'error');
                }
            } catch (err) {
                failed++;
                addLog(`❌ ${current}/${total} — ${user.nome} (${user.email}) — Erro de rede`, 'error');
            }

            // Atualizar progresso
            const pct = Math.round((current / total) * 100);
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressText').textContent = `${current} / ${total}`;
        }

        showDone(sent, failed);
    });

    function addLog(text, type) {
        const log = document.getElementById('sendLog');
        const line = document.createElement('div');
        line.style.padding = '4px 0';
        line.style.borderBottom = '1px solid rgba(255,255,255,0.03)';

        if (type === 'success') line.style.color = '#86efac';
        else if (type === 'error') line.style.color = '#fca5a5';
        else if (type === 'warn') line.style.color = '#fcd34d';

        line.textContent = text;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function showDone(sent, failed) {
        document.getElementById('headerEmoji').textContent = failed === 0 ? '✅' : '⚠️';
        document.getElementById('headerTitle').textContent = 'Envio Concluído';
        document.getElementById('headerSubtitle').textContent = `${sent} enviado(s)` + (failed > 0 ? ` — ${failed} falha(s)` : '');
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('progressBar').style.background = failed === 0
            ? 'linear-gradient(90deg, #10b981, #059669)'
            : 'linear-gradient(90deg, #f59e0b, #d97706)';
        document.getElementById('doneSection').style.display = 'block';
    }
</script>

<?php require_once 'includes/footer.php'; ?>