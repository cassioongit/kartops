<?php
/**
 * =====================================================
 * EDITAR PILOTO - KartOps
 * =====================================================
 */
require_once 'config/config.php';
require_once 'includes/auth_session.php';
require_once 'includes/image_helper.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: pilotos.php');
    exit;
}

$pdo = getDBConnection();

// 1. BUSCAR DADOS ATUAIS DO PILOTO
$stmt = $pdo->prepare("SELECT * FROM pilotos WHERE id = ?");
$stmt->execute([$id]);
$piloto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$piloto) {
    header('Location: pilotos.php');
    exit;
}

// 2. VERIFICAÇÃO DE PERMISSÃO
$userRole = strtolower($_SESSION['user_role'] ?? 'visitante');
$isAdmin = in_array($userRole, ['admin', 'owner']);
$isSelf = (!empty($usuario['id_piloto']) && $usuario['id_piloto'] == $id);

if (!$isAdmin && !$isSelf) {
    header('Location: home.php');
    exit;
}

$pageTitle = 'Editar Piloto - ' . ($piloto['apelido'] ?: $piloto['nome']);
require_once 'includes/header.php';

$msg = '';
$equipes = $pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $apelido = trim($_POST['apelido']);
    $bio = trim($_POST['bio']);
    $instagram = trim($_POST['instagram']);
    $foto_url = trim($_POST['foto_url']);
    
    // Tratamento de Upload de Foto
    $removeBg = isset($_POST['remove_bg']) && $_POST['remove_bg'] == '1';
    
    if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedPath = uploadAndOptimizeImage($_FILES['foto_file'], 'images/fotos/pilotos/', 'pilot_' . $id, 1000, 1000, 85, $removeBg);
        if ($uploadedPath) {
            $foto_url = $uploadedPath;
        }
    }

    // Admins podem trocar equipe e categoria
    if ($isAdmin) {
        $equipe_id = !empty($_POST['equipe_id']) ? $_POST['equipe_id'] : null;
        $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
    } else {
        // Piloto comum mantém o que já tinha
        $equipe_id = $piloto['equipe_id'];
        $categoria_id = $piloto['categoria_id'];
    }

    if (empty($nome) || empty($categoria_id)) {
        $msg = "<div style='color: #ff4444; margin-bottom: 20px;'>Nome e Categoria são obrigatórios!</div>";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE pilotos SET nome = ?, apelido = ?, equipe_id = ?, categoria_id = ?, bio = ?, instagram = ?, foto = ? WHERE id = ?");
            $stmt->execute([$nome, $apelido, $equipe_id, $categoria_id, $bio, $instagram, $foto_url, $id]);
            
            echo "<script>alert('Piloto atualizado com sucesso!'); window.location.href='piloto.php?id=$id';</script>";
            exit;
        } catch (PDOException $e) {
            $msg = "<div style='color: #ff4444; margin-bottom: 20px;'>Erro: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div style="max-width: 800px; margin: 120px auto; color: white; padding: 0 20px;">
    <h2 style="font-family: 'Archivo Black', sans-serif; font-size: 2.2rem; margin-bottom: 10px;">EDITAR PERFIL</h2>
    <p style="color: rgba(255,255,255,0.6); margin-bottom: 30px;">Atualize as informações do piloto <strong><?= htmlspecialchars($piloto['nome']) ?></strong></p>
    
    <a href="piloto.php?id=<?= $id ?>" style="color: #00b9ff; text-decoration: none; margin-bottom: 20px; display: inline-block;">← Voltar para Perfil</a>

    <?= $msg ?>

    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: rgba(255,255,255,0.05); padding: 40px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
        
        <div style="grid-column: span 2;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">NOME COMPLETO *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($piloto['nome']) ?>" required style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
        </div>

        <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">APELIDO / NOME DE GUERRA</label>
            <input type="text" name="apelido" value="<?= htmlspecialchars($piloto['apelido'] ?? '') ?>" style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
        </div>

        <div style="grid-column: span 2;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">FOTO:</label>
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <input type="file" name="foto_file" accept="image/*" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px;">
                <input type="text" name="foto_url" value="<?= htmlspecialchars($piloto['foto'] ?? '') ?>" placeholder="Ou cole a URL..." style="flex: 1.5; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
                
                <label style="display: flex; align-items: center; gap: 8px; background: rgba(0, 185, 255, 0.1); padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(0, 185, 255, 0.3); cursor: pointer; color: #00b9ff; font-weight: 700; font-size: 0.85rem;">
                    <input type="checkbox" name="remove_bg" value="1" style="width: 18px; height: 18px; accent-color: #00b9ff;">
                    Remover Fundo (IA)
                </label>
            </div>
            <p style="margin-top: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.4);">
                <i class="fas fa-magic"></i> Se a flag "Remover Fundo" estiver ativa, as fotos enviadas serão recortadas automaticamente via IA.
            </p>
        </div>

        <?php if ($isAdmin): ?>
        <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">CATEGORIA *</label>
            <select name="categoria_id" required style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $piloto['categoria_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">EQUIPE</label>
            <select name="equipe_id" style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
                <option value="">Sem Equipe</option>
                <?php foreach ($equipes as $eq): ?>
                    <option value="<?= $eq['id'] ?>" <?= $eq['id'] == $piloto['equipe_id'] ? 'selected' : '' ?>><?= htmlspecialchars($eq['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div style="grid-column: span 2;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">BIO / FRASE DE EFEITO</label>
            <textarea name="bio" rows="4" style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem; resize: none;"><?= htmlspecialchars($piloto['bio'] ?? '') ?></textarea>
        </div>

        <div style="grid-column: span 2;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: rgba(255,255,255,0.7);">INSTAGRAM (EX: @USUARIO)</label>
            <input type="text" name="instagram" value="<?= htmlspecialchars($piloto['instagram'] ?? '') ?>" placeholder="@..." style="width: 100%; padding: 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; font-size: 1rem;">
        </div>

        <button type="submit" style="grid-column: span 2; background: linear-gradient(135deg, #00b9ff, #0072ff); color: white; font-weight: 900; border: none; padding: 18px; border-radius: 8px; cursor: pointer; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 20px; box-shadow: 0 10px 20px rgba(0, 185, 255, 0.2); transition: all 0.3s;">
            SALVAR ALTERAÇÕES
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
