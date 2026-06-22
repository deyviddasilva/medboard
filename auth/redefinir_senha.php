<?php
session_start();
require_once '../config/database.php';

$pdo     = conectar();
$erro    = '';
$sucesso = '';
$token   = $_GET['token'] ?? $_POST['token'] ?? '';

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
    $erro = 'Este link é inválido ou já expirou. Solicite um novo.';
}

// processa a nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {

    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {

        $nova_senha  = $_POST['nova_senha']      ?? '';
        $confirmacao = $_POST['confirmar_senha'] ?? '';

        if (empty($nova_senha) || empty($confirmacao)) {
            $erro = 'Preencha todos os campos.';
        } elseif (strlen($nova_senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($nova_senha !== $confirmacao) {
            $erro = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $pdo->prepare("
                UPDATE usuarios 
                SET senha = ?, reset_token = NULL, reset_token_expira = NULL
                WHERE id_usuario = ?
            ")->execute([$hash, $usuario['id_usuario']]);

            $sucesso = 'Senha redefinida com sucesso! Você já pode fazer login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        if (localStorage.getItem('medboard-tema') === 'dark') {
            document.documentElement.classList.add('dark-preload');
        }
    </script>
    <title>Redefinir Senha — MedBoard</title>
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
            <p>Criar nova senha</p>
        </div>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
            <p style="text-align:center; margin-top: 16px;">
                <a href="login.php" class="btn-primary btn-block" style="text-decoration:none; text-align:center;">
                    Ir para o login
                </a>
            </p>
        <?php elseif ($token_valido): ?>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">

                <div class="campo">
                    <label for="nova_senha">Nova senha</label>
                    <div class="input-com-icone">
                        <input type="password" id="nova_senha" name="nova_senha"
                               placeholder="mínimo 6 caracteres" required>
                        <button type="button" onclick="toggleCampo('nova_senha', this)">👁</button>
                    </div>
                </div>

                <div class="campo">
                    <label for="confirmar_senha">Confirmar nova senha</label>
                    <div class="input-com-icone">
                        <input type="password" id="confirmar_senha" name="confirmar_senha"
                               placeholder="••••••••" required>
                        <button type="button" onclick="toggleCampo('confirmar_senha', this)">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-block">
                    Redefinir senha
                </button>
            </form>

        <?php else: ?>
            <p style="text-align:center; margin-top: 16px;">
                <a href="esqueci_senha.php">Solicitar novo link →</a>
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