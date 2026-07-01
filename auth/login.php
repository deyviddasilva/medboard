<?php
session_start();

$msg_timeout = $_SESSION['msg_timeout'] ?? '';
unset($_SESSION['msg_timeout']);

require_once '../config/database.php';

// detecta idioma do browser como fallback antes do login
if (empty($_SESSION['idioma'])) {
    $lang_browser = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2);
    $_SESSION['idioma'] = ($lang_browser === 'es') ? 'es' : 'pt-br';
}

require_once '../includes/i18n.php';

$erro = '';

if (isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = __('login_erro_campos');

        } elseif (!verificar_rate_limit($email)) {
            $erro = __('login_erro_muitas_tentativas');

        } else {
            $pdo  = conectar();
            $stmt = $pdo->prepare('
                SELECT id_usuario, nome, senha, idioma FROM usuarios WHERE email = ?
            ');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                resetar_tentativas($email);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nome']       = $usuario['nome'];
                $_SESSION['idioma']     = $usuario['idioma'] ?? 'pt-br';
                header('Location: ../index.php');
                exit;
            } else {
                registrar_tentativa_falha($email);
                $restantes = tentativas_restantes($email);
                $erro = $restantes > 0
                    ? __('login_erro_credenciais') . " {$restantes} " . __('login_tentativas_restantes')
                    : __('login_conta_bloqueada');
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
    <title><?= __('login_titulo') ?> — MedBoard</title>
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
            <p><?= __('login_subtitulo') ?></p>
        </div>

        <?php if ($msg_timeout): ?>
            <div class="alerta alerta-aviso">
                ⏱ <?= htmlspecialchars($msg_timeout) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token"
                value="<?= gerar_csrf_token() ?>">

            <div class="campo">
                <label for="email"><?= __('campo_email') ?></label>
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
                <label for="senha"><?= __('login_campo_senha') ?></label>
                <div class="input-com-icone">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="••••••••"
                        required
                    >
                    <button type="button" id="btn-ver-senha" onclick="toggleSenha()"
                            title="<?= __('login_mostrar_ocultar_senha') ?>">
                        👁
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-block">
                <?= __('login_entrar') ?>
            </button>
        </form>

        <p style="text-align:center; margin-top: 16px; font-size: 13px;">
            <a href="esqueci_senha.php"><?= __('login_esqueci_senha') ?></a>
        </p>

    </div>

    <script>
        function toggleSenha() {
            const input = document.getElementById('senha');
            const btn   = document.getElementById('btn-ver-senha');

            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
                btn.title = '<?= __('login_ocultar_senha') ?>';
            } else {
                input.type = 'password';
                btn.textContent = '👁';
                btn.title = '<?= __('login_mostrar_senha') ?>';
            }
        }
    </script>
</body>
</html>