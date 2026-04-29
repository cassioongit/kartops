<?php
/**
 * MODELO DE CONFIGURAÇÃO (Rename para mail_config.php)
 */

// Seus dados SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu_email@gmail.com');
define('SMTP_PASS', 'sua_senha_de_app');

// Configurações de Remetente
define('MAIL_FROM', 'noreply@kartops.com');
define('MAIL_NAME', 'KartOps');

// Debug
define('MAIL_DEBUG', false);
?>