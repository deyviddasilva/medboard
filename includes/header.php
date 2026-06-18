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
        <button class="btn-tema" id="btn-tema" onclick="toggleTema()" title="Alternar tema">
            <span id="icone-tema">🌙</span>
        </button>
    </div>
</header>

<script>
// aplica o tema salvo assim que a página carrega
(function() {
    const temaSalvo = localStorage.getItem('medboard-tema');
    if (temaSalvo === 'dark') {
        document.body.classList.add('dark');
        document.getElementById('icone-tema').textContent = '☀️';
    }
})();

function toggleTema() {
    const body  = document.body;
    const icone = document.getElementById('icone-tema');

    body.classList.toggle('dark');

    if (body.classList.contains('dark')) {
        localStorage.setItem('medboard-tema', 'dark');
        icone.textContent = '☀️';
    } else {
        localStorage.setItem('medboard-tema', 'light');
        icone.textContent = '🌙';
    }
}
</script>