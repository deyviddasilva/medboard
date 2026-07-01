<?php
session_start();
require_once '../config/database.php';

// idioma antes do login
if (empty($_SESSION['idioma'])) {
    $lang_browser = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2);
    $_SESSION['idioma'] = ($lang_browser === 'es') ? 'es' : 'pt-br';
}

require_once '../includes/i18n.php';

$pdo   = conectar();
$erro  = '';
$sucesso = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// valida o token
$stmt = $pdo->prepare("
    SELECT id_usuario, nome, reset_token_expira 
    FROM usuarios 
    WHERE reset_token = ?
");
$stmt->execute([$token]);
$usuario = $stmt->fetch();

$token_valido = $usuario && strtotime($usuario['reset_token_expira']) > time();

if (!$token_valido) {
    $erro = __('redefinir_erro_link_invalido');
}

// processa a nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {

    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {

        $nova_senha  = $_POST['nova_senha']      ?? '';
        $confirmacao = $_POST['confirmar_senha'] ?? '';

        if (empty($nova_senha) || empty($confirmacao)) {
            $erro = __('erro_campos_obrigatorios');
        } elseif (strlen($nova_senha) < 6) {
            $erro = __('erro_senha_minimo_6');
        } elseif ($nova_senha !== $confirmacao) {
            $erro = __('redefinir_erro_senhas_nao_coincidem');
        } else {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $pdo->prepare("
                UPDATE usuarios 
                SET senha = ?, reset_token = NULL, reset_token_expira = NULL
                WHERE id_usuario = ?
            ")->execute([$hash, $usuario['id_usuario']]);

            $sucesso = __('redefinir_sucesso');
        }
    }
}
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
    <title><?= __('redefinir_titulo') ?> — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-body">
<script>
if (localStorage.getItem('medboard-tema') === 'dark') {
    document.body.classList.add('dark');
}
</script>

    <div class="login-container">

        <div class="login-logo">
            <h1>🩺 MedBoard</h1>
            <p><?= __('redefinir_subtitulo') ?></p>
        </div>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
            <p style="text-align:center; margin-top: 16px;">
                <a href="login.php" class="btn-primary btn-block"
                   style="text-decoration:none; text-align:center;">
                    <?= __('redefinir_ir_login') ?>
                </a>
            </p>
        <?php elseif ($token_valido): ?>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">

                <div class="campo">
                    <label for="nova_senha"><?= __('nova_senha') ?></label>
                    <div class="input-com-icone">
                        <input type="password" id="nova_senha" name="nova_senha"
                               placeholder="<?= __('placeholder_minimo_6') ?>" required>
                        <button type="button" onclick="toggleCampo('nova_senha', this)">👁</button>
                    </div>
                </div>

                <div class="campo">
                    <label for="confirmar_senha"><?= __('confirmar_nova_senha') ?></label>
                    <div class="input-com-icone">
                        <input type="password" id="confirmar_senha" name="confirmar_senha"
                               placeholder="••••••••" required>
                        <button type="button" onclick="toggleCampo('confirmar_senha', this)">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-block">
                    <?= __('redefinir_btn') ?>
                </button>
            </form>

        <?php else: ?>
            <p style="text-align:center; margin-top: 16px;">
                <a href="esqueci_senha.php"><?= __('redefinir_solicitar_novo_link') ?></a>
            </p>
        <?php endif; ?>

    </div>

<script>
function toggleCampo(id, btn) {
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