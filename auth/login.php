<?php
session_start();
// mensagem de timeout
$msg_timeout = $_SESSION['msg_timeout'] ?? '';
unset($_SESSION['msg_timeout']);
require_once '../config/database.php';

$erro = '';

// se já estiver logado, manda direto pra home
if (isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

// quando o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = 'Preencha e-mail e senha.';

        } elseif (!verificar_rate_limit($email)) {
            $erro = '⚠️ Muitas tentativas. Tente novamente em 15 minutos.';

        } else {
            $pdo  = conectar();
            $stmt = $pdo->prepare('
                SELECT id_usuario, nome, senha FROM usuarios WHERE email = ?
            ');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                resetar_tentativas($email);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nome']       = $usuario['nome'];
                header('Location: ../index.php');
                exit;
            } else {
                registrar_tentativa_falha($email);
                $restantes = tentativas_restantes($email);
                $erro = $restantes > 0
                    ? "E-mail ou senha incorretos. {$restantes} tentativa(s) restante(s)."
                    : '⚠️ Conta bloqueada por 15 minutos.';
            }
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
    <title>Login — MedBoard</title>
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
            <p>Painel da Dra. <?= htmlspecialchars($_SESSION['nome'] ?? '') ?></p>
        </div>

        <?php if ($erro): ?>
            <?php if ($msg_timeout): ?>
                <div class="alerta alerta-aviso">
                    ⏱ <?= htmlspecialchars($msg_timeout) ?>
                </div>
            <?php endif; ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" 
                value="<?= gerar_csrf_token() ?>">
            <div class="campo">
                <label for="email">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="seu@email.com"
                    value="<?= htmlspecialchars($email ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <div class="input-com-icone">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="••••••••"
                        required
                    >
                    <button type="button" id="btn-ver-senha" onclick="toggleSenha()"
                            title="Mostrar/ocultar senha">
                        👁
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-block">Entrar</button>
        </form>

    </div>
    <script>
        function toggleSenha() {
            const input = document.getElementById('senha');
            const btn   = document.getElementById('btn-ver-senha');

            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
                btn.title = 'Ocultar senha';
            } else {
                input.type = 'password';
                btn.textContent = '👁';
                btn.title = 'Mostrar senha';
            }
        }
    </script>       
</body>
</html>