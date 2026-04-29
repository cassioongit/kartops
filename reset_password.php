<?php
/**
 * REDIRECIONAR PARA RECUPERAÇÃO
 * Este arquivo recebe o link do email e redireciona para forgot-password com passo correto
 */

$token = $_GET['token'] ?? '';

if ($token) {
    header("Location: forgot-password.php?step=reset&token=" . urlencode($token));
    exit;
} else {
    header("Location: forgot-password.php");
    exit;
}
?>