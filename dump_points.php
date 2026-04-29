<?php
require_once __DIR__ . '/config/config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM tabela_pontuacao ORDER BY categoria, posicao LIMIT 50");
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($dados);
