<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

verificar_sessao();

$pdo        = conectar();
$id_usuario = $_SESSION['id_usuario'];
$erro       = '';
$sucesso    = '';

// busca dados atuais
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

// -----------------------------------------------
// AÇÃO: ATUALIZAR DADOS PESSOAIS
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'dados') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida.';
    } else {
        $nome  = trim($_POST['nome']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nome) || empty($email)) {
            $erro = 'Nome e e-mail são obrigatórios.';
        } else {
            // verifica se email já existe em outro usuário
            $stmt = $pdo->prepare("
                SELECT id_usuario FROM usuarios
                WHERE email = ? AND id_usuario != ?
            ");
            $stmt->execute([$email, $id_usuario]);

            if ($stmt->fetch()) {
                $erro = 'Este e-mail já está em uso.';
            } else {
                $pdo->prepare("
                    UPDATE usuarios SET nome = ?, email = ?
                    WHERE id_usuario = ?
                ")->execute([$nome, $email, $id_usuario]);

                $_SESSION['nome'] = $nome;
                $sucesso = 'Dados atualizados com sucesso!';

                // recarrega usuário
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                $usuario = $stmt->fetch();
            }
        }
    }
}

// -----------------------------------------------
// AÇÃO: ALTERAR SENHA
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'senha') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida.';
    } else {
        $senha_atual = $_POST['senha_atual']     ?? '';
        $nova_senha  = $_POST['nova_senha']      ?? '';
        $confirmacao = $_POST['confirmar_senha'] ?? '';

        if (empty($senha_atual) || empty($nova_senha) || empty($confirmacao)) {
            $erro = 'Preencha todos os campos de senha.';
        } elseif (!password_verify($senha_atual, $usuario['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif (strlen($nova_senha) < 6) {
            $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($nova_senha !== $confirmacao) {
            $erro = 'A confirmação não confere com a nova senha.';
        } else {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $pdo->prepare("
                UPDATE usuarios SET senha = ? WHERE id_usuario = ?
            ")->execute([$hash, $id_usuario]);
            $sucesso = 'Senha alterada com sucesso!';
        }
    }
}

$titulo_pagina = 'Configurações';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="layout">

<?php include '../includes/sidebar.php'; ?>

<div class="conteudo">

    <?php include '../includes/header.php'; ?>

    <main class="main">

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <div class="config-layout">

            <!-- DADOS PESSOAIS -->
            <div class="card">
                <div class="card-header">
                    <h3>👩‍⚕️ Dados pessoais</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="dados">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label>Nome completo *</label>
                            <input type="text" name="nome" required
                                   value="<?= htmlspecialchars($usuario['nome']) ?>">
                        </div>

                        <div class="campo campo-full">
                            <label>E-mail *</label>
                            <input type="email" name="email" required
                                   value="<?= htmlspecialchars($usuario['email']) ?>">
                        </div>

                        <div class="campo campo-full">
                            <button type="submit" class="btn-primary">
                                💾 Salvar dados
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ALTERAR SENHA -->
            <div class="card">
                <div class="card-header">
                    <h3>🔒 Alterar senha</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="senha">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label>Senha atual *</label>
                            <div class="input-com-icone">
                                <input type="password" name="senha_atual"
                                       id="senha_atual"
                                       placeholder="••••••••" required>
                                <button type="button"
                                        onclick="toggleCampoSenha('senha_atual', this)">
                                    👁
                                </button>
                            </div>
                        </div>

                        <div class="campo">
                            <label>Nova senha *</label>
                            <div class="input-com-icone">
                                <input type="password" name="nova_senha"
                                       id="nova_senha"
                                       placeholder="mínimo 6 caracteres" required>
                                <button type="button"
                                        onclick="toggleCampoSenha('nova_senha', this)">
                                    👁
                                </button>
                            </div>
                        </div>

                        <div class="campo">
                            <label>Confirmar nova senha *</label>
                            <div class="input-com-icone">
                                <input type="password" name="confirmar_senha"
                                       id="confirmar_senha"
                                       placeholder="••••••••" required>
                                <button type="button"
                                        onclick="toggleCampoSenha('confirmar_senha', this)">
                                    👁
                                </button>
                            </div>
                        </div>

                        <div class="campo campo-full">
                            <button type="submit" class="btn-primary">
                                🔒 Alterar senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- INFORMAÇÕES DO SISTEMA -->
            <div class="card">
                <div class="card-header">
                    <h3>ℹ️ Informações do sistema</h3>
                </div>
                <div class="card-body">
                    <div class="info-sistema">
                        <div class="info-item">
                            <span class="info-label">Sistema</span>
                            <span class="info-valor">MedBoard v2.0</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usuária</span>
                            <span class="info-valor">
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Conta criada em</span>
                            <span class="info-valor">
                                <?= date('d/m/Y', strtotime($usuario['criado_em'])) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Última atualização</span>
                            <span class="info-valor">
                                <?= date('d/m/Y H:i', strtotime($usuario['atualizado_em'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>

<script>
function toggleCampoSenha(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁';
    }
}
</script>

</body>
</html>