<?php
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
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
        <a href="/sistema_medico/auth/logout.php" class="nav-item sair">
            <span class="nav-icon">🚪</span> Sair
        </a>
    </div>

</aside>