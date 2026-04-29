/**
 * =====================================================
 * KARTOPS - JavaScript
 * =====================================================
 */

/* ===== INDEX.PHP - COUNTDOWN TIMER ===== */
/**
 * Função para inicializar o countdown timer
 * Usado em: index.php
 */
function initCountdown(targetDateString) {
    const targetDate = new Date(targetDateString).getTime();

    function updateCountdown() {
        const now = new Date().getTime();
        const distance = targetDate - now;

        // Se já passou da data
        if (distance < 0) {
            document.getElementById('days').textContent = '00';
            document.getElementById('hours').textContent = '00';
            document.getElementById('minutes').textContent = '00';
            document.getElementById('seconds').textContent = '00';
            return;
        }

        // Calcular tempo restante
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Atualizar elementos no DOM
        document.getElementById('days').textContent = String(days).padStart(2, '0');
        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
    }

    // Atualizar imediatamente
    updateCountdown();

    // Atualizar a cada segundo
    setInterval(updateCountdown, 1000);
}

/* ===== GLOBAL - INICIALIZAÇÃO ===== */
/**
 * Inicializar quando o DOM estiver pronto
 * Aplicado em todas as páginas que incluem este script
 */
document.addEventListener('DOMContentLoaded', function () {
    // INDEX.PHP - Inicializar countdown se existir
    const countdownElement = document.getElementById('countdown');
    if (countdownElement && countdownElement.dataset.target) {
        initCountdown(countdownElement.dataset.target);
    }
});
