<?php
/**
 * Shared Footer Component
 * Standardizes the 3-line footer across the whole application.
 */
?>
<div class="footer-shared"
    style="text-align: center; margin-top: 30px; color: #70757f; font-size: 13px; font-family: 'Inter', sans-serif; line-height: 1.6;">
    <div style="margin-bottom: 2px;">
        KartOps &copy; <?= date('Y') ?> - Versão <?= defined('APP_VERSION') ? APP_VERSION : '1.6' ?> &middot;
    </div>
    <div style="margin-bottom: 8px;">
        Desenvolvido por <a href="https://cassioalexandre.com.br" target="_blank"
            style="color: #00f2ea; text-decoration: none; font-weight: 600;">cassioalexandre</a>
    </div>
    <div style="display: flex; justify-content: center; gap: 15px; font-size: 12px; margin-top: 5px;">
        <a href="termos_servico.php" style="color: #00f2ea; text-decoration: none;">Termos</a>
        <a href="politica_privacidade.php" style="color: #00f2ea; text-decoration: none;">Privacidade</a>
    </div>
</div>