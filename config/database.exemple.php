<?php

// -----------------------------------------------
// COPIE ESTE ARQUIVO PARA config/database.php
// e preencha com suas credenciais locais
// -----------------------------------------------

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_medico');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

function conectar(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHAR;

            $opcoes = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);

        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('Erro ao conectar com o banco de dados. Tente novamente.');
        }
    }

    return $pdo;
}