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
            <span class="nav-icon">🏠</span> <?= __('menu_home') ?>
        </a>
        <a href="/sistema_medico/pages/agenda.php"
           class="nav-item <?= $pagina_atual === 'agenda.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📅</span> <?= __('menu_agenda') ?>
        </a>
        <a href="/sistema_medico/pages/registro_dia.php"
           class="nav-item <?= $pagina_atual === 'registro_dia.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📝</span> <?= __('menu_registro') ?>
        </a>
        <a href="/sistema_medico/pages/atendimentos.php"
           class="nav-item <?= $pagina_atual === 'atendimentos.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">👥</span> <?= __('menu_atendimentos') ?>
        </a>
        <a href="/sistema_medico/pages/locais.php"
           class="nav-item <?= $pagina_atual === 'locais.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📍</span> <?= __('menu_locais') ?>
        </a>
        <a href="/sistema_medico/pages/relatorios.php"
           class="nav-item <?= $pagina_atual === 'relatorios.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">📊</span> <?= __('menu_relatorios') ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/sistema_medico/pages/lembretes.php"
           class="nav-item <?= $pagina_atual === 'lembretes.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">🔔</span> <?= __('menu_lembretes') ?>
        </a>
        <a href="/sistema_medico/pages/configuracoes.php"
           class="nav-item <?= $pagina_atual === 'configuracoes.php' ? 'ativo' : '' ?>">
            <span class="nav-icon">⚙️</span> <?= __('menu_configuracoes') ?>
        </a>
        <a href="/sistema_medico/auth/logout.php" class="nav-item sair">
            <span class="nav-icon">🚪</span> <?= __('sair') ?>
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
            'index.php'          => __('menu_home'),
            'agenda.php'         => __('menu_agenda'),
            'registro_dia.php'   => __('menu_registro'),
            'atendimentos.php'   => __('menu_atendimentos'),
            'locais.php'         => __('menu_locais'),
            'relatorios.php'     => __('menu_relatorios'),
            'lembretes.php'      => __('menu_lembretes'),
            'configuracoes.php'  => __('menu_configuracoes'),
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
        <span><?= __('menu_home') ?></span>
    </a>

    <a href="/sistema_medico/pages/agenda.php"
       class="mobile-nav-item <?= $pagina_atual === 'agenda.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">📅</span>
        <span><?= __('menu_agenda') ?></span>
    </a>

    <a href="/sistema_medico/pages/registro_dia.php"
       class="mobile-nav-item <?= $pagina_atual === 'registro_dia.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">📝</span>
        <span><?= __('menu_registro') ?></span>
    </a>

    <a href="/sistema_medico/pages/atendimentos.php"
       class="mobile-nav-item <?= $pagina_atual === 'atendimentos.php' ? 'ativo' : '' ?>">
        <span class="mobile-nav-icon">👥</span>
        <span><?= __('menu_atendimentos') ?></span>
    </a>

    <button class="mobile-nav-item" onclick="toggleMaisMenu()" id="btn-mais">
        <span class="mobile-nav-icon">☰</span>
        <span><?= __('menu_mais') ?></span>
    </button>

</nav>

<!-- PAINEL "MAIS" MOBILE -->
<div class="mobile-mais-overlay" id="mais-overlay" onclick="fecharMaisMenu()"></div>

<div class="mobile-mais-painel" id="mais-painel">

    <div class="mobile-mais-header">
        <span><?= __('menu_titulo') ?></span>
        <button onclick="fecharMaisMenu()" class="mobile-mais-fechar">✕</button>
    </div>

    <div class="mobile-mais-lista">

        <a href="/sistema_medico/pages/locais.php"
           class="mobile-mais-item <?= $pagina_atual === 'locais.php' ? 'ativo' : '' ?>">
            <span>📍</span> <?= __('menu_locais') ?>
        </a>

        <a href="/sistema_medico/pages/relatorios.php"
           class="mobile-mais-item <?= $pagina_atual === 'relatorios.php' ? 'ativo' : '' ?>">
            <span>📊</span> <?= __('menu_relatorios') ?>
        </a>

        <a href="/sistema_medico/pages/lembretes.php"
           class="mobile-mais-item <?= $pagina_atual === 'lembretes.php' ? 'ativo' : '' ?>">
            <span>🔔</span> <?= __('menu_lembretes') ?>
        </a>

        <a href="/sistema_medico/pages/configuracoes.php"
           class="mobile-mais-item <?= $pagina_atual === 'configuracoes.php' ? 'ativo' : '' ?>">
            <span>⚙️</span> <?= __('menu_configuracoes') ?>
        </a>

        <a href="/sistema_medico/auth/logout.php"
           class="mobile-mais-item sair">
            <span>🚪</span> <?= __('sair') ?>
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