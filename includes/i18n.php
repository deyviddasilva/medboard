<?php
// -----------------------------------------------
// SISTEMA DE TRADUÇÃO (i18n)
// -----------------------------------------------
function carregar_idioma(): array {
    $idioma  = $_SESSION['idioma'] ?? 'pt-br';
    $idioma  = strtolower($idioma);
    $arquivo = __DIR__ . '/../lang/' . $idioma . '.php';

    if (!file_exists($arquivo)) {
        $arquivo = __DIR__ . '/../lang/pt-br.php';
    }

    return require $arquivo;
}

$GLOBALS['traducoes'] = carregar_idioma();

// função de tradução — uso: __('chave')
function __(string $chave): string {
    return $GLOBALS['traducoes'][$chave] ?? $chave;
}