<?php
require_once 'config/config.php';
require_once 'includes/classificacao_helper.php';
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT p.id, c.nome as categoria_nome FROM pilotos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ?");
$stmt->execute(['19c03d64-f972-11f0-8ead-eef805f27145']);
$piloto = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($piloto);

$catNome = $piloto['categoria_nome'] ?? '';
$filterParam = (stripos($catNome, 'Master') !== false) ? 'Master' : 'Challenger';
var_dump($filterParam);
$rankingGlobal = getClassificationData($pdo, $filterParam);
foreach($rankingGlobal as $idx => $rnk) {
    if($rnk['id'] === '19c03d64-f972-11f0-8ead-eef805f27145') {
        var_dump("FOUND!", $rnk);
    }
}
?>
