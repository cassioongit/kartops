<?php
/**
 * =====================================================
 * PERFIL DO USUÁRIO - KartOps
 * Estilo Google Account
 * =====================================================
 * Modo Leitura (padrão) e Modo Edição (?edit=1)
 */

// Configurações da página
$pageTitle = 'Meu Perfil';
$additionalCSS = ['/css/perfil.css'];

$error = '';
$success = (isset($_GET['success']) && $_GET['success'] == '1') ? 'Perfil atualizado com sucesso!' : '';

// O header.php já inicia sessão e busca $usuario
require_once 'includes/header.php';
require_once 'includes/csrf.php';
require_once 'includes/image_helper.php';

// Verificar se usuário está logado
if (!$usuario) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Determinar modo: leitura ou edição
$modoEdicao = isset($_GET['edit']) && $_GET['edit'] == '1';

// Bloquear edição para visitantes
if ($modoEdicao && (isset($_SESSION['is_guest']) && $_SESSION['is_guest'])) {
    echo "<script>window.location.href='perfil.php';</script>"; // Redirecionar para modo leitura
    exit;
}


// Processar formulário de atualização (só no modo edição)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $modoEdicao) {
    if (!validateCsrfToken()) {
        $error = "Token CSRF inválido. Por favor, recarregue a página.";
    } else {
        $nome = trim($_POST['nome']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        // Edição de avatar removida.
        $avatar_url = $usuario['avatar_url'];

        if (empty($error) && empty($nome)) {
            $error = 'O nome não pode estar vazio';
        } elseif (empty($error) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        }

        // Se não há erros, prossegue com banco de dados
        if (empty($error)) {
            try {
                $pdo = getDBConnection();

                // Verificar se email já está em uso por outro usuário
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $usuario['id']]);

                if ($stmt->fetch()) {
                    $error = 'Este email já está em uso por outro usuário';
                } else {
                    // Campos que todos podem editar
                    $updateFields = [
                        'nome' => $nome,
                        'email' => $email,
                        'avatar_url' => $avatar_url ?: null,
                        'atualizado_em' => date('Y-m-d H:i:s')
                    ];

                    // Se for admin, pode alterar campos adicionais
                    if ($usuario['role'] === 'Admin') {
                        $updateFields['role'] = $_POST['role'] ?? $usuario['role'];
                        $updateFields['ativo'] = $_POST['ativo'] ?? $usuario['ativo'];
                        $updateFields['id_piloto'] = !empty($_POST['id_piloto']) ? $_POST['id_piloto'] : null;
                    }

                    // Construir query de update
                    $setClause = [];
                    $params = [];
                    foreach ($updateFields as $field => $value) {
                        $setClause[] = "$field = ?";
                        $params[] = $value;
                    }
                    $params[] = $usuario['id'];

                    $sql = "UPDATE usuarios SET " . implode(', ', $setClause) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);

                    if ($stmt->execute($params)) {
                        $success = 'Perfil atualizado com sucesso!';

                        // Atualizar sessão
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $nome;
                        if (isset($updateFields['role'])) {
                            $_SESSION['user_role'] = $updateFields['role'];
                        }

                        // Recarregar dados do usuário
                        $stmt = $pdo->prepare("SELECT id, nome, email, avatar_url, role, ativo, id_piloto, criado_em, atualizado_em FROM usuarios WHERE id = ?");
                        $stmt->execute([$usuario['id']]);
                        $usuario = $stmt->fetch();

                        // Voltar para modo leitura após salvar via JS (já que os headers HTML foram enviados)
                        echo "<script>window.location.href='perfil.php?success=1';</script>";
                        exit;
                    } else {
                        $error = 'Erro ao atualizar perfil';
                    }
                }
            } catch (Exception $e) {
                $error = 'Erro ao processar sua solicitação';
                error_log("Erro no perfil: " . $e->getMessage());
            }
        }
    }
}

// Buscar lista de pilotos (para select de id_piloto) - só se for admin e modo edição
$pilotos = [];
if ($usuario['role'] === 'Admin' && $modoEdicao) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, nome FROM pilotos ORDER BY nome");
        $stmt->execute();
        $pilotos = $stmt->fetchAll();
    } catch (Exception $e) {
        // Ignorar erro se tabela não existir
    }
}
?>

<!-- Conteúdo da Página -->
<div class="perfil-container">

    <!-- Profile Header -->
    <div class="perfil-header">
        <div class="perfil-header-info">
            <h1 class="perfil-title">
                <?= htmlspecialchars($usuario['nome']) ?>
                <?php if (!isset($_SESSION['is_guest']) || !$_SESSION['is_guest']): ?>
                    <a href="perfil.php?edit=1" class="perfil-action-icon <?= $modoEdicao ? 'disabled' : '' ?>"
                        title="Editar Perfil">
                        <i class="fas fa-pencil-alt"></i>
                    </a>
                    <a href="change_password.php" class="perfil-action-icon" title="Alterar Senha">
                        <i class="fas fa-key"></i>
                    </a>
                <?php endif; ?>
            </h1>
            <div class="perfil-badges-row">
                <span class="perfil-badge"><?= htmlspecialchars($usuario['role']) ?></span>
                <?php if (!empty($usuario['id_piloto'])): ?>
                    <span class="perfil-badge badge-piloto">Piloto</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($modoEdicao): ?>
        <!-- ==================== MODO EDIÇÃO ==================== -->
        <form method="POST" class="perfil-form" enctype="multipart/form-data">
            <?= generateCsrfField() ?>

            <!-- Seção: Informações Básicas -->
            <div class="form-section">
                <h2 class="form-section-title">Informações Básicas</h2>

                <div class="form-group">
                    <label class="form-label" for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="form-input"
                        value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-input"
                        value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    <div class="form-hint">Usado para fazer login no sistema</div>
                </div>


            </div>

            <!-- Seção: Configurações Administrativas (Apenas Admin) -->
            <?php if ($usuario['role'] === 'Admin'): ?>
                <div class="form-section">
                    <h2 class="form-section-title">Configurações Administrativas</h2>

                    <div class="form-group">
                        <label class="form-label" for="role">Tipo de Usuário</label>
                        <select id="role" name="role" class="form-select">
                            <option value="Usuário" <?= $usuario['role'] === 'Usuário' ? 'selected' : '' ?>>Usuário</option>
                            <option value="Colaborador" <?= $usuario['role'] === 'Colaborador' ? 'selected' : '' ?>>Colaborador
                            </option>
                            <option value="Admin" <?= $usuario['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ativo">Status da Conta</label>
                        <select id="ativo" name="ativo" class="form-select">
                            <option value="1" <?= $usuario['ativo'] ? 'selected' : '' ?>>Ativa</option>
                            <option value="0" <?= !$usuario['ativo'] ? 'selected' : '' ?>>Inativa</option>
                        </select>
                    </div>

                    <?php if (count($pilotos) > 0): ?>
                        <div class="form-group">
                            <label class="form-label" for="id_piloto">Vincular a Piloto</label>
                            <select id="id_piloto" name="id_piloto" class="form-select">
                                <option value="">Nenhum piloto vinculado</option>
                                <?php foreach ($pilotos as $piloto): ?>
                                    <option value="<?= htmlspecialchars($piloto['id']) ?>" <?= $usuario['id_piloto'] === $piloto['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($piloto['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint">Vincular este usuário a um piloto existente</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Seção: Informações do Sistema (Somente Leitura) -->
            <div class="form-section">
                <h2 class="form-section-title">Informações do Sistema</h2>

                <div class="form-group">
                    <label class="form-label">
                        Membro desde
                        <span class="readonly-badge">Somente leitura</span>
                    </label>
                    <input type="text" class="form-input" value="<?= date('d/m/Y H:i', strtotime($usuario['criado_em'])) ?>"
                        readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Última atualização
                        <span class="readonly-badge">Somente leitura</span>
                    </label>
                    <input type="text" class="form-input"
                        value="<?= date('d/m/Y H:i', strtotime($usuario['atualizado_em'])) ?>" readonly>
                </div>
            </div>

            <!-- Ações -->
            <div class="form-actions">
                <button type="submit" class="btn-submit">Salvar Alterações</button>
                <a href="perfil.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>

    <?php else: ?>
        <!-- ==================== MODO LEITURA ==================== -->
        <div class="perfil-view">

            <!-- Informações Básicas -->
            <div class="view-section">
                <h2 class="view-section-title">Informações Pessoais</h2>

                <div class="view-item">
                    <div class="view-icon icon-blue">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">Nome Completo</div>
                        <div class="view-value"><?= htmlspecialchars($usuario['nome']) ?></div>
                    </div>
                </div>

                <div class="view-item">
                    <div class="view-icon icon-purple">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">E-mail</div>
                        <div class="view-value"><?= htmlspecialchars($usuario['email']) ?></div>
                    </div>
                </div>

            </div>

            <!-- Informações do Sistema -->
            <div class="view-section">
                <h2 class="view-section-title">Conta & Sistema</h2>

                <div class="view-item">
                    <div class="view-icon icon-orange">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">Tipo de Usuário</div>
                        <div class="view-value">
                            <span class="view-badge"><?= htmlspecialchars($usuario['role']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="view-item">
                    <div class="view-icon icon-green">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">Status da Conta</div>
                        <div class="view-value">
                            <?= ($usuario['ativo'] ?? 1) ? '<span class="status-active">✓ Ativa</span>' : '<span class="status-inactive">✗ Inativa</span>' ?>
                        </div>
                    </div>
                </div>

                <div class="view-item">
                    <div class="view-icon icon-amber">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">Membro desde</div>
                        <div class="view-value">
                            <?= !empty($usuario['criado_em']) ? date('d/m/Y H:i', strtotime($usuario['criado_em'])) : 'N/A' ?>
                        </div>
                    </div>
                </div>

                <div class="view-item">
                    <div class="view-icon icon-amber">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="view-text">
                        <div class="view-label">Última atualização</div>
                        <div class="view-value">
                            <?= !empty($usuario['atualizado_em']) ? date('d/m/Y H:i', strtotime($usuario['atualizado_em'])) : 'N/A' ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($usuario['id_piloto'])): ?>
                    <div class="view-item">
                        <div class="view-icon icon-red">
                            <i class="fas fa-helmet-safety"></i>
                        </div>
                        <div class="view-text">
                            <div class="view-label">Piloto Vinculado</div>
                            <div class="view-value">ID: <?= htmlspecialchars($usuario['id_piloto']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ações removidas conforme solicitado -->
        <?php endif; ?>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</div>