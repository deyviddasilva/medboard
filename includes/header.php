<?php
// pega o dia da semana em português
$dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$dia_semana = $dias[date('w')];
$data_hoje = $dia_semana . ', ' . date('d/m/Y');
?>
<header class="topbar">
    <div class="topbar-left">
        <h2 class="topbar-titulo"><?= $titulo_pagina ?? 'Home' ?></h2>
    </div>
    <div class="topbar-right">
        <span class="topbar-data">📆 <?= $data_hoje ?></span>
        <span class="topbar-usuario">👩‍⚕️ <?= htmlspecialchars($_SESSION['nome']) ?></span>
    </div>
</header>