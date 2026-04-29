<?php
/**
 * =====================================================
 * CADASTRAR PILOTO - KartOps
 * =====================================================
 */
require_once 'config/config.php';
require_once 'includes/auth_session.php';
require_once 'includes/image_helper.php'; // Inicia a sessão corretamente

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array(strtolower($_SESSION['user_role']), ['admin', 'owner'])) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Cadastrar Piloto';
require_once 'includes/header.php';

$pdo = getDBConnection();
$msg = '';

$equipes = $pdo->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $apelido = trim($_POST['apelido']);
    $equipe_id = !empty($_POST['equipe_id']) ? $_POST['equipe_id'] : null;
    $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
    $bio = trim($_POST['bio']);
    $instagram = trim($_POST['instagram']);
    $foto_url = trim($_POST['foto_url']);

    // Tratamento de Upload de Foto
    $removeBg = isset($_POST['remove_bg']) && $_POST['remove_bg'] == '1';
    if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedPath = uploadAndOptimizeImage($_FILES['foto_file'], 'images/fotos/pilotos/', 'pilot_new', 1000, 1000, 85, $removeBg);
        if ($uploadedPath) {
            $foto_url = $uploadedPath;
        }
    }

    if (empty($nome) || empty($categoria_id)) {
        $msg = "<div style='color: #ff4444; margin-bottom: 20px;'>Nome e Categoria são obrigatórios!</div>";
    } else {
        try {
            // Se foto vier num formato de upload futuro, trate aqui. Por ora é URL ou string
            $stmt = $pdo->prepare("INSERT INTO pilotos (nome, apelido, equipe_id, categoria_id, bio, instagram, foto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $apelido, $equipe_id, $categoria_id, $bio, $instagram, $foto_url]);
            
            echo "<script>alert('Piloto cadastrado com sucesso!'); window.location.href='pilotos.php';</script>";
            exit;
        } catch (PDOException $e) {
            $msg = "<div style='color: #ff4444; margin-bottom: 20px;'>Erro: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div style="max-width: 600px; margin: 150px auto; color: white;">
    <h2 style="font-family: 'Archivo Black', sans-serif; font-size: 2rem; margin-bottom: 20px;">CADASTRAR PILOTO</h2>
    
    <a href="pilotos.php" style="color: #00b9ff; text-decoration: none; margin-bottom: 20px; display: inline-block;">← Voltar para a lista</a>

    <?= $msg ?>

    <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 15px; background: rgba(255,255,255,0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
        <div>
            <label style="display: block; margin-bottom: 5px;">Nome Completo *</label>
            <input type="text" name="nome" required style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; margin-bottom: 5px;">Apelido</label>
            <input type="text" name="apelido" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
        </div>

        <div>
            <label style="display: block; margin-bottom: 5px;">Categoria *</label>
            <select name="categoria_id" required style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
                <option value="">Selecione...</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display: block; margin-bottom: 5px;">Equipe</label>
            <select name="equipe_id" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
                <option value="">Sem Equipe</option>
                <?php foreach ($equipes as $eq): ?>
                    <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="grid-column: span 2;">
            <label style="display: block; margin-bottom: 5px;">Foto do Piloto</label>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="file" name="foto_file" accept="image/*" style="flex: 1; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
                <input type="text" name="foto_url" placeholder="Ou cole a URL..." style="flex: 1.5; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
                
                <label style="display: flex; align-items: center; gap: 8px; background: rgba(0, 185, 255, 0.1); padding: 5px 12px; border-radius: 6px; border: 1px solid rgba(0, 185, 255, 0.3); cursor: pointer; color: #00b9ff; font-weight: 700; font-size: 0.8rem;">
                    <input type="checkbox" name="remove_bg" value="1" style="width: 16px; height: 16px; accent-color: #00b9ff;">
                    Remover Fundo (IA)
                </label>
            </div>
        </div>

        <div>
            <label style="display: block; margin-bottom: 5px;">Bio</label>
            <textarea name="bio" rows="4" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;"></textarea>
        </div>

        <div>
            <label style="display: block; margin-bottom: 5px;">Instagram (ex: @usuario)</label>
            <input type="text" name="instagram" placeholder="@..." style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px;">
        </div>

        <button type="submit" style="background: #00b9ff; color: black; font-weight: bold; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-size: 1.1rem; margin-top: 10px;">
            SALVAR NOVO PILOTO
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
