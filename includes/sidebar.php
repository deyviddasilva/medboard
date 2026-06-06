<?php
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>

<!-- SIDEBAR DESKTOP -->
<aside class="sidebar">

    <div class="sidebar-logo">
        <span>🩺</span>
        <strong>MedBoard</strong>
    </div>

    <nav class="sidebar-nav">
        <a href="/sistema_medico/index.php"
           class="nav-item <?= $pagina_atual === 'index.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">🏠</span> Home
        </a>
        <a href="/sistema_medico/pages/agenda.php"
           class="nav-item <?= $pagina_atual === 'agenda.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📅</span> Agenda
        </a>
        <a href="/sistema_medico/pages/registro_dia.php"
           class="nav-item <?= $pagina_atual === 'registro_dia.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📝</span> Registro do dia
        </a>
        <a href="/sistema_medico/pages/atendimentos.php"
           class="nav-item <?= $pagina_atual === 'atendimentos.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">👥</span> Atendimentos
        </a>
        <a href="/sistema_medico/pages/locais.php"
           class="nav-item <?= $pagina_atual === 'locais.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📍</span> Locais
        </a>
        <a href="/sistema_medico/pages/relatorios.php"
           class="nav-item <?= $pagina_atual === 'relatorios.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📊</span> Relatórios
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/sistema_medico/pages/lembretes.php"
           class="nav-item <?= $pagina_atual === 'lembretes.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">🔔</span> Lembretes
        </a>
        <a href="/sistema_medico/pages/configuracoes.php"
           class="nav-item <?= $pagina_atual === 'configuracoes.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">⚙️</span> Configurações
        </a>
        <a href="/sistema_medico/auth/logout.php" class="nav-item sair">
            <span class="nav-icon">🚪</span> Sair
        </a>
    </div>

</aside>

<!-- TOPBAR MOBILE -->
<header class="mobile-topbar">
    <div class="mobile-logo">
        <span>🩺</span>
        <strong>MedBoard</strong>
    </div>
    <span class="mobile-pagina">
        <?php
        $nomes = [
            'index.php'          => 'Home',
            'agenda.php'         => 'Agenda',
            'registro_dia.php'   => 'Registro do Dia',
            'atendimentos.php'   => 'Atendimentos',
            'locais.php'         => 'Locais',
            'relatorios.php'     => 'Relatórios',
            'lembretes.php'      => 'Lembretes',
            'configuracoes.php'  => 'Configurações',
        ];
        echo $nomes[$pagina_atual] ?? 'MedBoard';
        ?>
    </span>
</header>

<!-- BARRA INFERIOR MOBILE -->
<nav class="mobile-nav">

    <a href="/sistema_medico/index.php"
       class="mobile-nav-item <?= $pagina_atual === 'index.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">🏠</span>
        <span>Início</span>
    </a>

    <a href="/sistema_medico/pages/agenda.php"
       class="mobile-nav-item <?= $pagina_atual === 'agenda.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">📅</span>
        <span>Agenda</span>
    </a>

    <a href="/sistema_medico/pages/registro_dia.php"
       class="mobile-nav-item <?= $pagina_atual === 'registro_dia.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">📝</span>
        <span>Registro</span>
    </a>

    <a href="/sistema_medico/pages/atendimentos.php"
       class="mobile-nav-item <?= $pagina_atual === 'atendimentos.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">👥</span>
        <span>Atendimentos</span>
    </a>

    <button class="mobile-nav-item" onclick="toggleMaisMenu()" id="btn-mais">
        <span class="mobile-nav-icon">☰</span>
        <span>Mais</span>
    </button>

</nav>

<!-- PAINEL "MAIS" MOBILE -->
<div class="mobile-mais-overlay" id="mais-overlay" onclick="fecharMaisMenu()"></div>

<div class="mobile-mais-painel" id="mais-painel">

    <div class="mobile-mais-header">
        <span>Menu</span>
        <button onclick="fecharMaisMenu()" class="mobile-mais-fechar">✕</button>
    </div>

    <div class="mobile-mais-lista">

        <a href="/sistema_medico/pages/locais.php"
           class="mobile-mais-item <?= $pagina_atual === 'locais.php' ? 'ativo' : '' ?>">
            <span>📍</span> Locais de trabalho
        </a>

        <a href="/sistema_medico/pages/relatorios.php"
           class="mobile-mais-item <?= $pagina_atual === 'relatorios.php' ? 'ativo' : '' ?>">
            <span>📊</span> Relatórios
        </a>

        <a href="/sistema_medico/pages/lembretes.php"
           class="mobile-mais-item <?= $pagina_atual === 'lembretes.php' ? 'ativo' : '' ?>">
            <span>🔔</span> Lembretes
        </a>

        <a href="/sistema_medico/pages/configuracoes.php"
           class="mobile-mais-item <?= $pagina_atual === 'configuracoes.php' ? 'ativo' : '' ?>">
            <span>⚙️</span> Configurações
        </a>

        <a href="/sistema_medico/auth/logout.php"
           class="mobile-mais-item sair">
            <span>🚪</span> Sair
        </a>

    </div>

</div>

<script>
function toggleMaisMenu() {
    const painel  = document.getElementById('mais-painel');
    const overlay = document.getElementById('mais-overlay');
    const btn     = document.getElementById('btn-mais');

    painel.classList.toggle('aberto');
    overlay.classList.toggle('ativo');
    btn.classList.toggle('ativo');
}

function fecharMaisMenu() {
    document.getElementById('mais-painel').classList.remove('aberto');
    document.getElementById('mais-overlay').classList.remove('ativo');
    document.getElementById('btn-mais').classList.remove('ativo');
}
</script>