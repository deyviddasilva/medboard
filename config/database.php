<?php

// URL base do sistema
define('BASE_URL', '/sistema_medico/');

// -----------------------------------------------
// CONFIGURAÇÕES DO BANCO DE DADOS
// -----------------------------------------------
define('DB_HOST', 'localhost');       // servidor do banco
define('DB_NAME', 'sistema_medico'); // nome do banco que criamos
define('DB_USER', 'root');           // usuário do MySQL (padrão XAMPP)
define('DB_PASS', '');               // senha (vazia no XAMPP local)
define('DB_CHAR', 'utf8mb4');        // charset que definimos no banco

// -----------------------------------------------
// FUNÇÃO DE CONEXÃO
// -----------------------------------------------
function conectar(): PDO {
    static $pdo = null; // guarda a conexão aberta para reutilizar

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHAR;

            $opcoes = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // mostra erros reais
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // retorna array associativo
                PDO::ATTR_EMULATE_PREPARES   => false,                  // segurança contra SQL injection
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);

        } catch (PDOException $e) {
            // em produção, nunca exiba o erro real para o usuário
            error_log($e->getMessage()); // salva o erro no log do servidor
            die('Erro ao conectar com o banco de dados. Tente novamente.');
        }
    }

    return $pdo;
}

// -----------------------------------------------
// CSRF TOKEN
// -----------------------------------------------
function gerar_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validar_csrf_token(string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// -----------------------------------------------
// RATE LIMITING — LOGIN
// -----------------------------------------------
function verificar_rate_limit(string $email): bool {
    $chave       = 'login_tentativas_' . md5($email);
    $chave_tempo = 'login_bloqueio_' . md5($email);

    // se está bloqueado
    if (!empty($_SESSION[$chave_tempo])) {
        if (time() < $_SESSION[$chave_tempo]) {
            return false; // ainda bloqueado
        } else {
            // tempo expirou, reseta
            unset($_SESSION[$chave], $_SESSION[$chave_tempo]);
        }
    }

    return true;
}

function registrar_tentativa_falha(string $email): void {
    $chave       = 'login_tentativas_' . md5($email);
    $chave_tempo = 'login_bloqueio_' . md5($email);

    $_SESSION[$chave] = ($_SESSION[$chave] ?? 0) + 1;

    // após 5 tentativas bloqueia por 15 minutos
    if ($_SESSION[$chave] >= 5) {
        $_SESSION[$chave_tempo] = time() + 900;
        unset($_SESSION[$chave]);
    }
}

function resetar_tentativas(string $email): void {
    $chave       = 'login_tentativas_' . md5($email);
    $chave_tempo = 'login_bloqueio_' . md5($email);
    unset($_SESSION[$chave], $_SESSION[$chave_tempo]);
}

function tentativas_restantes(string $email): int {
    $chave = 'login_tentativas_' . md5($email);
    return max(0, 5 - ($_SESSION[$chave] ?? 0));
}