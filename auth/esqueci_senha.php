<?php
session_start();
require_once '../config/database.php';
require_once '../includes/mailer.php';

$pdo     = conectar();
$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $erro = 'Informe seu e-mail.';
        } else {

            $stmt = $pdo->prepare("SELECT id_usuario, nome FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            // por segurança, sempre mostra a mesma mensagem,
            // mesmo se o e-mail não existir no banco
            if ($usuario) {
                $token   = bin2hex(random_bytes(32));
                $expira  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $pdo->prepare("
                    UPDATE usuarios 
                    SET reset_token = ?, reset_token_expira = ?
                    WHERE id_usuario = ?
                ")->execute([$token, $expira, $usuario['id_usuario']]);

                $link = 'http://' . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['REQUEST_URI']) .
                        '/redefinir_senha.php?token=' . $token;

                enviar_email_recuperacao($email, $usuario['nome'], $link);
            }

            $sucesso = 'Se este e-mail estiver cadastrado, você receberá um link de recuperação em breve.';
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
    <title>Recuperar Senha — MedBoard</title>
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
            <p>Recuperação de senha</p>
        </div>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php else: ?>

        <p style="color: var(--color-text-muted); font-size: 13px; margin-bottom: 18px;">
            Informe o e-mail cadastrado. Enviaremos um link para você redefinir sua senha.
        </p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">

            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                       placeholder="seu@email.com" required autofocus>
            </div>

            <button type="submit" class="btn-primary btn-block">
                Enviar link de recuperação
            </button>
        </form>

        <?php endif; ?>

        <p style="text-align:center; margin-top: 20px; font-size: 13px;">
            <a href="login.php">← Voltar para o login</a>
        </p>

    </div>

</body>
</html>