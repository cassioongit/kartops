<?php
/**
 * API PILOTOS - KartOps
 * Fornece dados dos pilotos em formato JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
// Autenticação básica (leitura pode ser pública se necessário, mas vamos restringir por enquanto)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Se a requisição pedir apenas lista simples (id, nome, numero)
    $simple = isset($_GET['simple']) && $_GET['simple'] === 'true';

    if ($simple) {
        $sql = "SELECT p.id, p.nome, p.foto, c.nome as categoria_nome, p.categoria_id 
                FROM pilotos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id
                ORDER BY p.nome ASC";
    } else {
        $sql = "SELECT p.*, c.nome as categoria_nome, e.nome as equipe_nome 
                FROM pilotos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN equipes e ON p.equipe_id = e.id
                ORDER BY p.nome ASC";
    }

    $stmt = $pdo->query($sql);
    $pilotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $pilotos]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>