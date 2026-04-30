<?php
/**
 * =====================================================
 * API DE ONBOARDS - KartOps
 * =====================================================
 * Endpoints para CRUD de onboards (vídeos de corridas)
 */

// Iniciar buffer de saída para capturar warnings do PHP
ob_start();

// Carregar configurações ANTES de iniciar a sessão
@require_once __DIR__ . '/../config/config.php';

// Iniciar sessão DEPOIS de carregar as configurações
require_once __DIR__ . '/../includes/csrf.php';

// Limpar qualquer output (warnings) antes de enviar JSON
ob_clean();

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

// =====================================================
// DETERMINAR AÇÃO
// =====================================================
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? 'list';

// Ações públicas (não exigem login completo, visitante logado é suficiente, ou visitante não logado se quisermos liberar total)
// O sistema usa session_start e o visitante tem role 'Visitante' caso tenha logado como tal, ou pode não ter sessão.
$isPublicAction = ($method === 'GET' && in_array($action, ['list', 'etapas', 'pilotos']));

if (!isset($_SESSION['user_id']) && !$isPublicAction) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$usuarioLogado = null;
try {
    $pdo = getDBConnection();

    if (isset($_SESSION['user_id'])) {
        // Buscar dados do usuário logado
        $stmt = $pdo->prepare("SELECT id, nome, email, role, id_piloto FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuarioLogado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioLogado && !$isPublicAction) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
            exit;
        }

        // Visitantes logados só podem consultar (GET)
        if ($usuarioLogado && $usuarioLogado['role'] === 'Visitante' && $method !== 'GET') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Visitantes não têm acesso para criar ou alterar dados']);
            exit;
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
    exit;
}

// =====================================================
// FUNÇÕES AUXILIARES
// =====================================================

/**
 * Extrai o ID do vídeo de uma URL do YouTube
 */
function extractYoutubeVideoId($url)
{
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/live\/([a-zA-Z0-9_-]+)/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Busca o título do vídeo usando oEmbed do YouTube
 */
function getYoutubeVideoTitle($videoId)
{
    $url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['title'] ?? null;
}

/**
 * Verifica se é Admin ou Owner
 */
function isAdmin($usuario)
{
    return in_array($usuario['role'], ['Admin', 'Owner']);
}

// =====================================================
// PROTEÇÃO CSRF PARA ESCRITA
// =====================================================

// Validar CSRF para ações de escrita
if (in_array($action, ['create', 'delete', 'validate_url']) && $method === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
}

// =====================================================
// AÇÃO: LISTAR ONBOARDS
// =====================================================
if ($action === 'list' && $method === 'GET') {
    try {
        $sql = "
            SELECT 
                o.id,
                o.youtube_url,
                o.youtube_video_id,
                o.titulo,
                o.etapa_id,
                o.piloto_id,
                o.categoria_id,
                o.usuario_id,
                o.criado_em,
                e.nome as etapa_nome,
                e.data as etapa_data,
                p.nome as piloto_nome,
                p.foto as piloto_foto,
                eq.nome as equipe_nome,
                eq.cor as equipe_cor,
                c.nome as categoria_nome,
                u.nome as usuario_nome
            FROM onboards o
            INNER JOIN etapas e ON o.etapa_id = e.id
            INNER JOIN pilotos p ON o.piloto_id = p.id
            LEFT JOIN equipes eq ON p.equipe_id = eq.id
            LEFT JOIN categorias c ON o.categoria_id = c.id
            INNER JOIN usuarios u ON o.usuario_id = u.id
            ORDER BY e.data DESC, o.criado_em DESC
        ";

        $stmt = $pdo->query($sql);
        $onboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $onboards]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
    exit;
}

// =====================================================
// AÇÃO: BUSCAR ETAPAS PASSADAS
// =====================================================
if ($action === 'etapas' && $method === 'GET') {
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT id, nome, data, kartodromo 
            FROM etapas 
            WHERE data <= ? 
            ORDER BY data DESC
        ");
        $stmt->execute([$today]);
        $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $etapas]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar etapas']);
    }
    exit;
}

// =====================================================
// AÇÃO: BUSCAR PILOTOS (para Admin/Owner)
// =====================================================
if ($action === 'pilotos' && $method === 'GET') {
    try {
        // Se for Admin/Owner, retorna todos os pilotos
        // Se não, retorna apenas o piloto vinculado
        if ($usuarioLogado && isAdmin($usuarioLogado)) {
            $stmt = $pdo->query("
                SELECT p.id, p.nome, p.categoria_id, c.nome as categoria_nome
                FROM pilotos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                ORDER BY p.nome ASC
            ");
        } else {
            // Se não for admin (usuário normal ou visitante sem login)
            if (!$usuarioLogado || empty($usuarioLogado['id_piloto'])) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $stmt = $pdo->prepare("
                SELECT p.id, p.nome, p.categoria_id, c.nome as categoria_nome
                FROM pilotos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$usuarioLogado['id_piloto']]);
        }

        $pilotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $pilotos]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar pilotos']);
    }
    exit;
}

// =====================================================
// AÇÃO: VALIDAR URL DO YOUTUBE
// =====================================================
if ($action === 'validate_url' && $method === 'POST') {
    $url = trim($input['url'] ?? '');

    if (empty($url)) {
        echo json_encode(['success' => false, 'message' => 'URL não informada']);
        exit;
    }

    $videoId = extractYoutubeVideoId($url);

    if (!$videoId) {
        echo json_encode(['success' => false, 'message' => 'URL do YouTube inválida']);
        exit;
    }

    $titulo = getYoutubeVideoTitle($videoId);

    echo json_encode([
        'success' => true,
        'video_id' => $videoId,
        'titulo' => $titulo,
        'embed_url' => "https://www.youtube.com/embed/{$videoId}"
    ]);
    exit;
}

// =====================================================
// AÇÃO: CRIAR ONBOARD
// =====================================================
if ($action === 'create' && $method === 'POST') {
    $youtubeUrl = trim($input['youtube_url'] ?? '');
    $etapaId = trim($input['etapa_id'] ?? '');
    $pilotoId = trim($input['piloto_id'] ?? '');

    // Validações
    if (empty($youtubeUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'URL do YouTube é obrigatória']);
        exit;
    }

    if (empty($etapaId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Etapa é obrigatória']);
        exit;
    }

    if (empty($pilotoId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Piloto é obrigatório']);
        exit;
    }

    // Verificar permissão do piloto
    if (!isAdmin($usuarioLogado)) {
        if (empty($usuarioLogado['id_piloto']) || $usuarioLogado['id_piloto'] !== $pilotoId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Você só pode enviar onboards para o seu piloto']);
            exit;
        }
    }

    // Extrair video ID
    $videoId = extractYoutubeVideoId($youtubeUrl);
    if (!$videoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'URL do YouTube inválida']);
        exit;
    }

    // Buscar título do vídeo
    $titulo = getYoutubeVideoTitle($videoId);

    try {
        // Verificar se a etapa já ocorreu
        $stmt = $pdo->prepare("SELECT id, data FROM etapas WHERE id = ?");
        $stmt->execute([$etapaId]);
        $etapa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$etapa) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Etapa não encontrada']);
            exit;
        }

        // Comparação estrita apenas da DATA (Y-m-d)
        $etapaData = substr($etapa['data'], 0, 10);
        $hoje = date('Y-m-d');

        if ($etapaData > $hoje) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Só é possível enviar onboards para etapas já realizadas. (Data da etapa: ' . date('d/m/Y', strtotime($etapaData)) . ')'
            ]);
            exit;
        }

        // Verificar se o piloto existe e buscar categoria
        $stmt = $pdo->prepare("SELECT id, categoria_id FROM pilotos WHERE id = ?");
        $stmt->execute([$pilotoId]);
        $piloto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$piloto) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Piloto não encontrado']);
            exit;
        }

        // Verificar se já existe onboard para esta etapa/piloto
        $stmt = $pdo->prepare("SELECT id FROM onboards WHERE etapa_id = ? AND piloto_id = ?");
        $stmt->execute([$etapaId, $pilotoId]);

        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Já existe um onboard para este piloto nesta etapa']);
            exit;
        }

        // Inserir onboard
        $stmt = $pdo->prepare("
            INSERT INTO onboards (id, youtube_url, youtube_video_id, titulo, etapa_id, piloto_id, categoria_id, usuario_id)
            VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $youtubeUrl,
            $videoId,
            $titulo,
            $etapaId,
            $pilotoId,
            $piloto['categoria_id'],
            $usuarioLogado['id']
        ]);

        echo json_encode(['success' => true, 'message' => 'Onboard cadastrado com sucesso!']);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("[KartOps] Erro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
    }
    exit;
}

// =====================================================
// AÇÃO: EXCLUIR ONBOARD
// =====================================================
if ($action === 'delete' && $method === 'POST') {
    $onboardId = trim($input['id'] ?? '');

    if (empty($onboardId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do onboard é obrigatório']);
        exit;
    }

    try {
        // Buscar onboard
        $stmt = $pdo->prepare("SELECT id, usuario_id FROM onboards WHERE id = ?");
        $stmt->execute([$onboardId]);
        $onboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$onboard) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Onboard não encontrado']);
            exit;
        }

        // Verificar permissão: Admin/Owner pode excluir qualquer um, outros só o próprio
        if (!isAdmin($usuarioLogado) && $onboard['usuario_id'] !== $usuarioLogado['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Você só pode excluir seus próprios onboards']);
            exit;
        }

        // Excluir
        $stmt = $pdo->prepare("DELETE FROM onboards WHERE id = ?");
        $stmt->execute([$onboardId]);

        echo json_encode(['success' => true, 'message' => 'Onboard excluído com sucesso!']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir onboard']);
    }
    exit;
}

// Ação não reconhecida
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
?>