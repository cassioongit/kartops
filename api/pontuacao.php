<?php
/**
 * API PONTUAÇÃO - KartOps
 * Gerencia operações na tabela_pontuacao
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Iniciar sessão
require_once __DIR__ . '/../includes/csrf.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Buscar dados do usuário pelo BD (padrão consistente)
$pdo = getDBConnection();
$stmtUser = $pdo->prepare("SELECT id, nome, role FROM usuarios WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$allowedRoles = ['Admin', 'Colaborador', 'Owner'];
if (!in_array($usuario['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Apenas Admin e Owner podem escrever (Create, Update, Delete)
$canWrite = ($usuario['role'] === 'Admin' || $usuario['role'] === 'Owner');

// Ler corpo da requisição
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $pdo = getDBConnection();

    // Validar CSRF para ações de escrita
    if (in_array($action, ['create', 'update', 'delete'])) {
        if (!validateCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }
    }

    switch ($action) {
        case 'create':
        case 'update':
            if (!$canWrite) {
                throw new Exception('Permissão negada para editar.');
            }

            $id = $input['id'] ?? null;
            $categoria = $input['categoria'] ?? '';
            $peso = $input['peso'] ?? 1;
            $posicao = $input['posicao'] ?? 0;
            $sem1 = $input['primeiro_semestre'] ?? 0;
            $jul = $input['julho'] ?? 0;
            $ago = $input['agosto'] ?? 0;
            $set = $input['setembro'] ?? 0;
            $out = $input['outubro'] ?? 0;
            $nov = $input['novembro'] ?? 0;
            $dez = $input['dezembro'] ?? 0;

            if (empty($categoria) || empty($posicao)) {
                throw new Exception('Categoria e Posição são obrigatórios');
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO tabela_pontuacao (categoria, peso, posicao, primeiro_semestre, julho, agosto, setembro, outubro, novembro, dezembro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoria, $peso, $posicao, $sem1, $jul, $ago, $set, $out, $nov, $dez]);
            } else {
                $stmt = $pdo->prepare("UPDATE tabela_pontuacao SET categoria=?, peso=?, posicao=?, primeiro_semestre=?, julho=?, agosto=?, setembro=?, outubro=?, novembro=?, dezembro=? WHERE id=?");
                $stmt->execute([$categoria, $peso, $posicao, $sem1, $jul, $ago, $set, $out, $nov, $dez, $id]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'delete':
            if (!$canWrite) {
                throw new Exception('Permissão negada para excluir.');
            }

            $id = $input['id'] ?? null;
            if (!$id)
                throw new Exception('ID não fornecido');

            $stmt = $pdo->prepare("DELETE FROM tabela_pontuacao WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Ação inválida');
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>