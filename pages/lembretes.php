<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/i18n.php';

verificar_sessao();

$pdo        = conectar();
$id_usuario = $_SESSION['id_usuario'];
$erro       = '';
$sucesso    = '';

// -----------------------------------------------
// AÇÃO: CADASTRAR LEMBRETE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {
        $titulo = trim($_POST['titulo']    ?? '');
        $data   = $_POST['data_lembrete'] ?? '';
        $hora   = $_POST['hora_lembrete'] ?? '';
        $desc   = trim($_POST['descricao'] ?? '');

        if (empty($titulo) || empty($data)) {
            $erro = __('erro_titulo_data_obrigatorios');
        } else {
            $pdo->prepare("
                INSERT INTO lembretes
                    (id_usuario, titulo, descricao, data_lembrete, hora_lembrete)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $id_usuario, $titulo,
                $desc ?: null, $data,
                $hora ?: null
            ]);
            $sucesso = __('sucesso_lembrete_cadastrado');
        }
    }
}

// -----------------------------------------------
// AÇÃO: CONCLUIR LEMBRETE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'concluir') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {
        $id = (int)($_POST['id_lembrete'] ?? 0);
        $pdo->prepare("
            UPDATE lembretes SET status_lembrete = 'concluido'
            WHERE id_lembrete = ? AND id_usuario = ?
        ")->execute([$id, $id_usuario]);
        $sucesso = __('sucesso_lembrete_concluido');
    }
}

// -----------------------------------------------
// AÇÃO: EXCLUIR LEMBRETE
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {
        $id = (int)($_POST['id_lembrete'] ?? 0);
        $pdo->prepare("
            DELETE FROM lembretes
            WHERE id_lembrete = ? AND id_usuario = ?
        ")->execute([$id, $id_usuario]);
        $sucesso = __('sucesso_lembrete_removido');
    }
}

// -----------------------------------------------
// BUSCA: LEMBRETES PENDENTES
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM lembretes
    WHERE id_usuario = ? AND status_lembrete = 'pendente'
    ORDER BY data_lembrete ASC, hora_lembrete ASC
");
$stmt->execute([$id_usuario]);
$pendentes = $stmt->fetchAll();

// -----------------------------------------------
// BUSCA: LEMBRETES CONCLUÍDOS (últimos 10)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM lembretes
    WHERE id_usuario = ? AND status_lembrete = 'concluido'
    ORDER BY data_lembrete DESC
    LIMIT 10
");
$stmt->execute([$id_usuario]);
$concluidos = $stmt->fetchAll();

$titulo_pagina = __('menu_lembretes');
$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <script>
        if (localStorage.getItem('medboard-tema') === 'dark') {
            document.documentElement.classList.add('dark-preload');
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('menu_lembretes') ?> — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="layout">
<script>
if (localStorage.getItem('medboard-tema') === 'dark') {
    document.body.classList.add('dark');
}
</script>

<?php include '../includes/sidebar.php'; ?>

<div class="conteudo">

    <?php include '../includes/header.php'; ?>

    <main class="main">

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <div class="grid-2">

            <!-- FORMULÁRIO -->
            <div class="card">
                <div class="card-header">
                    <h3>🔔 <?= __('novo_lembrete') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form-grid">
                        <input type="hidden" name="acao" value="cadastrar">
                        <input type="hidden" name="csrf_token"
                               value="<?= gerar_csrf_token() ?>">

                        <div class="campo campo-full">
                            <label><?= __('campo_titulo') ?> *</label>
                            <input type="text" name="titulo" required
                                   placeholder="<?= __('placeholder_titulo_lembrete') ?>">
                        </div>

                        <div class="campo">
                            <label><?= __('campo_data') ?> *</label>
                            <input type="date" name="data_lembrete"
                                   value="<?= $hoje ?>" required>
                        </div>

                        <div class="campo">
                            <label><?= __('campo_hora') ?></label>
                            <input type="time" name="hora_lembrete">
                        </div>

                        <div class="campo campo-full">
                            <label><?= __('campo_descricao') ?></label>
                            <textarea name="descricao" rows="3"
                                      placeholder="<?= __('placeholder_descricao_lembrete') ?>"></textarea>
                        </div>

                        <div class="campo campo-full">
                            <button type="submit" class="btn-primary btn-block">
                                + <?= __('adicionar_lembrete') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- LEMBRETES PENDENTES -->
            <div class="card">
                <div class="card-header">
                    <h3>⏳ <?= __('pendentes') ?></h3>
                    <span class="badge badge-agendado">
                        <?= count($pendentes) ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($pendentes): ?>
                        <?php foreach ($pendentes as $l):
                            $atrasado = $l['data_lembrete'] < $hoje;
                            $e_hoje   = $l['data_lembrete'] === $hoje;
                        ?>
                            <div class="lembrete-item <?= $atrasado ? 'atrasado' : ($e_hoje ? 'hoje' : '') ?>">
                                <div class="lembrete-info">
                                    <strong><?= htmlspecialchars($l['titulo']) ?></strong>
                                    <span>
                                        <?= date('d/m/Y', strtotime($l['data_lembrete'])) ?>
                                        <?= $l['hora_lembrete']
                                            ? __('as') . ' ' . date('H:i', strtotime($l['hora_lembrete']))
                                            : '' ?>
                                        <?php if ($atrasado): ?>
                                            <span class="badge badge-cancelado"><?= __('atrasado') ?></span>
                                        <?php elseif ($e_hoje): ?>
                                            <span class="badge badge-folga"><?= __('hoje') ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($l['descricao']): ?>
                                        <small><?= htmlspecialchars($l['descricao']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="lembrete-acoes">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="acao" value="concluir">
                                        <input type="hidden" name="id_lembrete"
                                               value="<?= $l['id_lembrete'] ?>">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= gerar_csrf_token() ?>">
                                        <button class="btn-mini btn-success"
                                                title="<?= __('marcar_concluido') ?>">✅</button>
                                    </form>

                                    <form method="POST" style="display:inline"
                                          onsubmit="return confirm('<?= __('confirmar_remover_lembrete') ?>')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id_lembrete"
                                               value="<?= $l['id_lembrete'] ?>">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= gerar_csrf_token() ?>">
                                        <button class="btn-mini btn-danger"
                                                title="<?= __('remover') ?>">🗑</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="vazio"><?= __('nenhum_lembrete_pendente') ?> ✅</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- CONCLUÍDOS -->
        <?php if ($concluidos): ?>
        <div class="card">
            <div class="card-header">
                <h3>✅ <?= __('concluidos_recentemente') ?></h3>
            </div>
            <div class="card-body">
                <?php foreach ($concluidos as $l): ?>
                    <div class="lembrete-item concluido">
                        <div class="lembrete-info">
                            <strong><?= htmlspecialchars($l['titulo']) ?></strong>
                            <span><?= date('d/m/Y', strtotime($l['data_lembrete'])) ?></span>
                        </div>
                        <form method="POST"
                              onsubmit="return confirm('<?= __('confirmar_remover_lembrete') ?>')">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id_lembrete"
                                   value="<?= $l['id_lembrete'] ?>">
                            <input type="hidden" name="csrf_token"
                                   value="<?= gerar_csrf_token() ?>">
                            <button class="btn-mini btn-danger">🗑</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>
</body>
</html>