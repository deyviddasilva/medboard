<?php
// -----------------------------------------------
// VERIFICAÇÃO DE AUTENTICAÇÃO E TIMEOUT DE SESSÃO
// -----------------------------------------------

// tempo máximo de inatividade: 2 horas (em segundos) = 7200
define('SESSION_TIMEOUT', 7200);

function verificar_sessao(): void {

    // se não está logado, redireciona para login
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }

    // verifica timeout de inatividade
    if (isset($_SESSION['ultima_atividade'])) {
        $inativo = time() - $_SESSION['ultima_atividade'];

        if ($inativo > SESSION_TIMEOUT) {
            // sessão expirada — limpa e redireciona
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['msg_timeout'] = 'Sua sessão expirou por inatividade. Faça login novamente.';
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit;
        }
    }

    // atualiza o tempo da última atividade
    $_SESSION['ultima_atividade'] = time();
}