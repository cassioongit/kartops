<?php
require_once 'config/config.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID da etapa não fornecido.");
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM etapas WHERE id = ?");
    $stmt->execute([$id]);
    $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etapa) {
        die("Etapa não encontrada.");
    }

    $dtStart = date('Ymd\THis', strtotime($etapa['data'] . ' ' . $etapa['hora']));
    $dtEnd = date('Ymd\THis', strtotime($etapa['data'] . ' ' . $etapa['hora']) + 3600); // 1 hora de duração padrão
    $dtStamp = date('Ymd\THis');
    $uid = uniqid() . "@kartops.com.br";
    $summary = "KartOps: " . $etapa['nome'];
    $location = $etapa['kartodromo'];
    $description = "Etapa do Campeonato KartOps. Local: " . $location;

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="etapa_kartops.ics"');

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//KartOps//Racing System//PT\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . $dtStamp . "\r\n";
    echo "DTSTART:" . $dtStart . "\r\n";
    echo "DTEND:" . $dtEnd . "\r\n";
    echo "SUMMARY:" . $summary . "\r\n";
    echo "LOCATION:" . $location . "\r\n";
    echo "DESCRIPTION:" . $description . "\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";

} catch (Exception $e) {
    die("Erro ao gerar calendário: " . $e->getMessage());
}
