<?php
session_start();
session_destroy(); // apaga todos os dados da sessão
header('Location: login.php');
exit;