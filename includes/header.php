<header class="topbar">
    <div class="topbar-left">
        <h2 class="topbar-titulo"><?= $titulo_pagina ?? 'Home' ?></h2>
    </div>

    <div class="topbar-right">
        <span class="topbar-data">📆 <span id="data-hoje"></span></span>
        <span class="topbar-usuario">👩‍⚕️ <?= htmlspecialchars($_SESSION['nome']) ?></span>
        <button class="btn-tema" id="btn-tema" onclick="toggleTema()" title="Alternar tema">
            <span id="icone-tema">🌙</span>
        </button>
    </div>
</header>

<script>
function atualizarDataUsuario() {
    const el = document.getElementById('data-hoje');
    if (!el) return;

    const agora = new Date();

    const formatado = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }).format(agora);

    const textoFinal = formatado.charAt(0).toUpperCase() + formatado.slice(1);
    el.textContent = textoFinal;
}

// aplica o tema salvo assim que a página carrega
(function () {
    const temaSalvo = localStorage.getItem('medboard-tema');

    if (temaSalvo === 'dark') {
        document.body.classList.add('dark');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const icone = document.getElementById('icone-tema');

        if (icone) {
            icone.textContent = document.body.classList.contains('dark') ? '☀️' : '🌙';
        }

        atualizarDataUsuario();
    });
})();

function toggleTema() {
    const body = document.body;
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