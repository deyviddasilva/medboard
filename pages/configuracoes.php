<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/i18n.php';

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
        $erro = __('erro_requisicao_invalida');
    } else {
        $nome  = trim($_POST['nome']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nome) || empty($email)) {
            $erro = __('erro_nome_email_obrigatorios');
        } else {
            // verifica se email já existe em outro usuário
            $stmt = $pdo->prepare("
                SELECT id_usuario FROM usuarios
                WHERE email = ? AND id_usuario != ?
            ");
            $stmt->execute([$email, $id_usuario]);

            if ($stmt->fetch()) {
                $erro = __('erro_email_em_uso');
            } else {
                $pdo->prepare("
                    UPDATE usuarios SET nome = ?, email = ?
                    WHERE id_usuario = ?
                ")->execute([$nome, $email, $id_usuario]);

                $_SESSION['nome'] = $nome;
                $sucesso = __('sucesso_dados_atualizados');

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
        $erro = __('erro_requisicao_invalida');
    } else {
        $senha_atual = $_POST['senha_atual']     ?? '';
        $nova_senha  = $_POST['nova_senha']      ?? '';
        $confirmacao = $_POST['confirmar_senha'] ?? '';

        if (empty($senha_atual) || empty($nova_senha) || empty($confirmacao)) {
            $erro = __('erro_preencha_campos_senha');
        } elseif (!password_verify($senha_atual, $usuario['senha'])) {
            $erro = __('erro_senha_atual_incorreta');
        } elseif (strlen($nova_senha) < 6) {
            $erro = __('erro_senha_minimo_6');
        } elseif ($nova_senha !== $confirmacao) {
            $erro = __('erro_confirmacao_nao_confere');
        } else {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $pdo->prepare("
                UPDATE usuarios SET senha = ? WHERE id_usuario = ?
            ")->execute([$hash, $id_usuario]);
            $sucesso = __('sucesso_senha_alterada');
        }
    }
}

// -----------------------------------------------
// AÇÃO: ALTERAR IDIOMA
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'idioma') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {
        $novo_idioma = $_POST['idioma'] ?? 'pt-br';

        if (!in_array($novo_idioma, ['pt-br', 'es'])) {
            $erro = __('erro_idioma_invalido');
        } else {
            $pdo->prepare("
                UPDATE usuarios SET idioma = ? WHERE id_usuario = ?
            ")->execute([$novo_idioma, $id_usuario]);

            $_SESSION['idioma'] = $novo_idioma;

            // recarrega traduções imediatamente com o novo idioma
            $GLOBALS['traducoes'] = carregar_idioma();

            // recarrega usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $usuario = $stmt->fetch();

            $sucesso = __('sucesso_idioma_alterado');
        }
    }
}

$titulo_pagina = __('menu_configuracoes');

$idiomas_disponiveis = [
    'pt-br' => __('idioma_portugues'),
    'es'    => __('idioma_espanhol'),
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <script>
        if (localStorage.getItem('medboard-tema') === 'dark') {
            document.documentElement.classList.add('dark-preload');
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('menu_configuracoes') ?> — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="layout">
<script>
if (localStorage.getItem('medboard-tema') === 'dark') {
    document.body.classList.add('dark');
}
</script>

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
                    <h3>👩‍⚕️ <?= __('dados_pessoais') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="dados">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label><?= __('nome_completo') ?> *</label>
                            <input type="text" name="nome" required
                                   value="<?= htmlspecialchars($usuario['nome']) ?>">
                        </div>

                        <div class="campo campo-full">
                            <label><?= __('campo_email') ?> *</label>
                            <input type="email" name="email" required
                                   value="<?= htmlspecialchars($usuario['email']) ?>">
                        </div>

                        <div class="campo campo-full">
                            <button type="submit" class="btn-primary">
                                💾 <?= __('salvar_dados') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- IDIOMA -->
            <div class="card">
                <div class="card-header">
                    <h3>🌎 <?= __('idioma_do_sistema') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="idioma">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label><?= __('selecione_idioma') ?></label>
                            <select name="idioma">
                                <?php foreach ($idiomas_disponiveis as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= strtolower($usuario['idioma'] ?? 'pt-br') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo campo-full">
                            <button type="submit" class="btn-primary">
                                🌎 <?= __('aplicar_idioma') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ALTERAR SENHA -->
            <div class="card">
                <div class="card-header">
                    <h3>🔒 <?= __('alterar_senha') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="senha">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label><?= __('senha_atual') ?> *</label>
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
                            <label><?= __('nova_senha') ?> *</label>
                            <div class="input-com-icone">
                                <input type="password" name="nova_senha"
                                       id="nova_senha"
                                       placeholder="<?= __('placeholder_minimo_6') ?>" required>
                                <button type="button"
                                        onclick="toggleCampoSenha('nova_senha', this)">
                                    👁
                                </button>
                            </div>
                        </div>

                        <div class="campo">
                            <label><?= __('confirmar_nova_senha') ?> *</label>
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
                                🔒 <?= __('alterar_senha') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- INFORMAÇÕES DO SISTEMA -->
            <div class="card">
                <div class="card-header">
                    <h3>ℹ️ <?= __('informacoes_sistema') ?></h3>
                </div>
                <div class="card-body">
                    <div class="info-sistema">
                        <div class="info-item">
                            <span class="info-label"><?= __('sistema') ?></span>
                            <span class="info-valor">MedBoard v3.0</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?= __('usuaria') ?></span>
                            <span class="info-valor">
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?= __('idioma_atual') ?></span>
                            <span class="info-valor">
                                <?= $idiomas_disponiveis[strtolower($usuario['idioma'] ?? 'pt-br')] ?? '—' ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?= __('conta_criada_em') ?></span>
                            <span class="info-valor">
                                <?= date('d/m/Y', strtotime($usuario['criado_em'])) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><?= __('ultima_atualizacao') ?></span>
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