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
// AÇÃO: SALVAR REGISTRO
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {

        $id_agenda           = (int)($_POST['id_agenda'] ?? 0);
        $pacientes_atendidos = (int)($_POST['pacientes_atendidos'] ?? 0);
        $faltas              = (int)($_POST['faltas'] ?? 0);
        $cancelamentos       = (int)($_POST['cancelamentos'] ?? 0);
        $encaixes            = (int)($_POST['encaixes'] ?? 0);
        $tempo_medio         = (int)($_POST['tempo_medio_consulta'] ?? 0);
        $hora_real_inicio    = $_POST['hora_real_inicio'] ?? null;
        $hora_real_fim       = $_POST['hora_real_fim'] ?? null;
        $situacao            = $_POST['situacao_dia'] ?? 'realizado';
        $obs                 = trim($_POST['observacao'] ?? '');

        if ($id_agenda === 0) {
            $erro = __('erro_selecione_turno');
        } elseif ($pacientes_atendidos < 0 || $faltas < 0 || $cancelamentos < 0 || $encaixes < 0) {
            $erro = __('erro_valores_negativos');
        } else {

            // verifica se o turno pertence ao usuário
            $stmt = $pdo->prepare("
                SELECT id_agenda FROM agenda_trabalho
                WHERE id_agenda = ? AND id_usuario = ?
            ");
            $stmt->execute([$id_agenda, $id_usuario]);

            if (!$stmt->fetch()) {
                $erro = __('erro_turno_invalido');
             } else {

                // calcula duração real se os horários forem informados
                $duracao_real = null;
                if (!empty($hora_real_inicio) && !empty($hora_real_fim)) {
                    $ini = strtotime($hora_real_inicio);
                    $fim = strtotime($hora_real_fim);
                    if ($fim < $ini) $fim += 86400; // virou meia noite
                    $duracao_real = (int)(($fim - $ini) / 60);
                }

                // verifica se já existe registro para este turno
                $stmt = $pdo->prepare("
                    SELECT id_registro FROM registro_diario WHERE id_agenda = ?
                ");
                $stmt->execute([$id_agenda]);
                $existente = $stmt->fetch();

                if ($existente) {
                    // ATUALIZA
                    $stmt = $pdo->prepare("
                        UPDATE registro_diario SET
                            pacientes_atendidos  = ?,
                            faltas               = ?,
                            cancelamentos        = ?,
                            encaixes             = ?,
                            tempo_medio_consulta = ?,
                            hora_real_inicio     = ?,
                             hora_real_fim        = ?,
                            duracao_real_minutos = ?,
                            situacao_dia         = ?,
                            observacao           = ?
                        WHERE id_agenda = ?
                    ");
                    $stmt->execute([
                        $pacientes_atendidos, $faltas, $cancelamentos,
                        $encaixes, $tempo_medio ?: null,
                        $hora_real_inicio ?: null, $hora_real_fim ?: null,
                        $duracao_real, $situacao, $obs ?: null,
                        $id_agenda
                    ]);
                    $sucesso = __('sucesso_registro_atualizado');
                } else {
                    // INSERE
                    $stmt = $pdo->prepare("
                        INSERT INTO registro_diario
                            (id_agenda, pacientes_atendidos, faltas, cancelamentos,
                             encaixes, tempo_medio_consulta, hora_real_inicio,
                             hora_real_fim, duracao_real_minutos, situacao_dia, observacao)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_agenda, $pacientes_atendidos, $faltas, $cancelamentos,
                        $encaixes, $tempo_medio ?: null,
                        $hora_real_inicio ?: null, $hora_real_fim ?: null,
                        $duracao_real, $situacao, $obs ?: null
                    ]);
                    $sucesso = __('sucesso_registro_salvo');
                }

                // atualiza status da agenda para concluido
                $pdo->prepare("
                    UPDATE agenda_trabalho SET status_agenda = 'concluido'
                    WHERE id_agenda = ?
                ")->execute([$id_agenda]);
            }
        }
    }
}

// -----------------------------------------------
// AÇÃO: EXCLUIR REGISTRO
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id_registro = (int)($_POST['id_registro'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT r.id_registro, r.id_agenda
        FROM registro_diario r
        JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
        WHERE r.id_registro = ? AND a.id_usuario = ?
    ");
    $stmt->execute([$id_registro, $id_usuario]);
    $reg = $stmt->fetch();

    if ($reg) {
        $pdo->prepare("DELETE FROM registro_diario WHERE id_registro = ?")
            ->execute([$id_registro]);

        // volta status da agenda para agendado
        $pdo->prepare("
            UPDATE agenda_trabalho SET status_agenda = 'agendado'
            WHERE id_agenda = ?
        ")->execute([$reg['id_agenda']]);

        $sucesso = __('sucesso_registro_removido');
    }
}

// -----------------------------------------------
// BUSCA: TURNOS SEM REGISTRO (para o formulário)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.id_agenda, a.data_trabalho, a.turno,
           a.hora_inicio, a.hora_fim, a.status_agenda,
           l.nome AS local_nome
    FROM agenda_trabalho a
    JOIN locais_trabalho l ON l.id_local = a.id_local
    LEFT JOIN registro_diario r ON r.id_agenda = a.id_agenda
    WHERE a.id_usuario = ?
      AND r.id_registro IS NULL
      AND a.status_agenda != 'cancelado'
      AND a.status_agenda != 'folga'
      AND a.data_trabalho <= CURDATE()
    ORDER BY a.data_trabalho DESC, a.hora_inicio ASC
");
$stmt->execute([$id_usuario]);
$turnos_pendentes = $stmt->fetchAll();

// -----------------------------------------------
// BUSCA: REGISTROS JÁ LANÇADOS (últimos 30 dias)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*,
           a.data_trabalho, a.turno, a.hora_inicio, a.hora_fim,
           l.nome AS local_nome, l.cor_identificacao
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho l ON l.id_local  = a.id_local
    WHERE a.id_usuario = ?
    ORDER BY a.data_trabalho DESC, a.hora_inicio DESC
    LIMIT 30
");
$stmt->execute([$id_usuario]);
$registros = $stmt->fetchAll();

// pré-carrega registro se vier id_agenda na URL
$preload = null;
if (!empty($_GET['id_agenda'])) {
    $id_pre = (int)$_GET['id_agenda'];
    $stmt   = $pdo->prepare("
        SELECT r.*, a.data_trabalho, a.turno, a.hora_inicio, a.hora_fim,
               l.nome AS local_nome
        FROM registro_diario r
        JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
        JOIN locais_trabalho l ON l.id_local  = a.id_local
        WHERE r.id_agenda = ? AND a.id_usuario = ?
    ");
    $stmt->execute([$id_pre, $id_usuario]);
    $preload = $stmt->fetch();
}

$titulo_pagina = __('registro_do_dia');

$situacoes = [
    'realizado'              => __('situacao_realizado'),
    'realizado_parcialmente' => __('situacao_parcial'),
    'nao_realizado'          => __('situacao_nao_realizado'),
    'substituido'            => __('situacao_substituido'),
];

$turnos_label = [
    'manha'             => __('turno_manha'),
    'tarde'             => __('turno_tarde'),
    'noite'             => __('turno_noite'),
    'manha/tarde'       => __('turno_manha_tarde'),
    'tarde/noite'       => __('turno_tarde_noite'),
    'manha/tarde/noite' => __('turno_dia_inteiro'),
];
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
    <title><?= __('registro_do_dia') ?> — MedBoard</title>
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

        <div class="registro-layout">

            <!-- COLUNA ESQUERDA: FORMULÁRIO -->
            <div class="registro-esquerda">
                <div class="card">
                    <div class="card-header">
                        <h3>📝 <?= __('lancar_registro_turno') ?></h3>
                    </div>
                    <div class="card-body">

                        <?php if (empty($turnos_pendentes) && !$preload): ?>
                            <div class="vazio">
                                <?= __('nenhum_turno_pendente_registro') ?> 🎉<br>
                                <small><?= __('todos_turnos_lancados') ?></small>
                            </div>
                        <?php else: ?>

                        <form method="POST" action="" class="form-registro">
                            <input type="hidden" name="csrf_token" 
                                value="<?= gerar_csrf_token() ?>">
                            <input type="hidden" name="acao" value="salvar">

                            <!-- SELEÇÃO DO TURNO -->
                            <div class="campo campo-full">
                                <label><?= __('campo_turno') ?> *</label>
                                <select name="id_agenda" id="select_turno" required
                                        onchange="preencherHorarios(this)">
                                    <option value=""><?= __('selecione_turno') ?>...</option>

                                    <?php if ($preload): ?>
                                        <option value="<?= $preload['id_agenda'] ?>"
                                                data-inicio="<?= $preload['hora_inicio'] ?>"
                                                data-fim="<?= $preload['hora_fim'] ?>"
                                                selected>
                                            <?= date('d/m/Y', strtotime($preload['data_trabalho'])) ?>
                                            — <?= htmlspecialchars($preload['local_nome']) ?>
                                            (<?= $turnos_label[$preload['turno']] ?? ucfirst($preload['turno']) ?>
                                            <?= date('H:i', strtotime($preload['hora_inicio'])) ?>
                                            <?= __('as') ?>
                                            <?= date('H:i', strtotime($preload['hora_fim'])) ?>)
                                        </option>
                                    <?php endif; ?>

                                    <?php foreach ($turnos_pendentes as $tp): ?>
                                        <?php if ($preload && $preload['id_agenda'] == $tp['id_agenda']) continue; ?>
                                        <option value="<?= $tp['id_agenda'] ?>"
                                                data-inicio="<?= $tp['hora_inicio'] ?>"
                                                data-fim="<?= $tp['hora_fim'] ?>">
                                            <?= date('d/m/Y', strtotime($tp['data_trabalho'])) ?>
                                            — <?= htmlspecialchars($tp['local_nome']) ?>
                                            (<?= $turnos_label[$tp['turno']] ?? ucfirst($tp['turno']) ?>
                                            <?= date('H:i', strtotime($tp['hora_inicio'])) ?>
                                            <?= __('as') ?>
                                            <?= date('H:i', strtotime($tp['hora_fim'])) ?>)
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <!-- CONTADORES -->
                            <div class="campo">
                                <label><?= __('pacientes_atendidos') ?></label>
                                <input type="number" name="pacientes_atendidos"
                                       min="0" value="<?= $preload['pacientes_atendidos'] ?? 0 ?>"
                                       class="input-numero">
                            </div>

                            <div class="campo">
                                <label><?= __('faltas') ?></label>
                                <input type="number" name="faltas"
                                       min="0" value="<?= $preload['faltas'] ?? 0 ?>"
                                       class="input-numero">
                            </div>

                            <div class="campo">
                                <label><?= __('cancelamentos') ?></label>
                                <input type="number" name="cancelamentos"
                                       min="0" value="<?= $preload['cancelamentos'] ?? 0 ?>"
                                       class="input-numero">
                            </div>

                            <div class="campo">
                                <label><?= __('encaixes') ?></label>
                                <input type="number" name="encaixes"
                                       min="0" value="<?= $preload['encaixes'] ?? 0 ?>"
                                       class="input-numero">
                            </div>

                            <div class="campo">
                                <label><?= __('tempo_medio_consulta') ?></label>
                                <input type="number" name="tempo_medio_consulta"
                                       min="1" placeholder="<?= __('placeholder_tempo') ?>"
                                       value="<?= $preload['tempo_medio_consulta'] ?? '' ?>"
                                       class="input-numero">
                            </div>

                            <div class="campo">
                                <label><?= __('situacao_do_dia') ?></label>
                                <select name="situacao_dia">
                                    <?php foreach ($situacoes as $val => $label): ?>
                                        <option value="<?= $val ?>"
                                            <?= ($preload['situacao_dia'] ?? 'realizado') === $val ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- HORÁRIOS REAIS -->
                            <div class="campo">
                                <label><?= __('hora_real_inicio') ?></label>
                                <input type="time" name="hora_real_inicio"
                                       id="hora_real_inicio"
                                       value="<?= $preload['hora_real_inicio'] ?? '' ?>">
                            </div>

                            <div class="campo">
                                <label><?= __('hora_real_fim') ?></label>
                                <input type="time" name="hora_real_fim"
                                       id="hora_real_fim"
                                       value="<?= $preload['hora_real_fim'] ?? '' ?>">
                            </div>

                            <!-- OBSERVAÇÃO -->
                            <div class="campo campo-full">
                                <label><?= __('observacao_do_dia') ?></label>
                                <textarea name="observacao" rows="3"
                                          placeholder="<?= __('placeholder_obs_dia') ?>"
                                ><?= htmlspecialchars($preload['observacao'] ?? '') ?></textarea>
                            </div>

                            <div class="campo campo-full">
                                <button type="submit" class="btn-primary btn-block">
                                    💾 <?= __('salvar_registro') ?>
                                </button>
                            </div>

                        </form>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA: HISTÓRICO -->
            <div class="registro-direita">
                <div class="card">
                    <div class="card-header">
                        <h3>📋 <?= __('ultimos_registros') ?></h3>
                        <span class="badge badge-agendado"><?= count($registros) ?> <?= __('registro_s') ?></span>
                    </div>
                    <div class="card-body">

                        <?php if ($registros): ?>
                            <?php foreach ($registros as $r): ?>
                                <div class="registro-item"
                                     style="border-left: 4px solid <?= $r['cor_identificacao'] ?>">

                                    <div class="registro-topo">
                                        <div>
                                            <strong><?= date('d/m/Y', strtotime($r['data_trabalho'])) ?></strong>
                                            <span class="registro-local">
                                                <?= htmlspecialchars($r['local_nome']) ?>
                                            </span>
                                        </div>
                                        <span class="badge badge-<?= $r['situacao_dia'] === 'realizado' ? 'concluido' : ($r['situacao_dia'] === 'nao_realizado' ? 'cancelado' : 'agendado') ?>">
                                            <?= $situacoes[$r['situacao_dia']] ?? $r['situacao_dia'] ?>
                                        </span>
                                    </div>

                                    <div class="registro-numeros">
                                        <div class="num-item">
                                            <span class="num-valor"><?= $r['pacientes_atendidos'] ?></span>
                                            <span class="num-label"><?= __('label_atendidos') ?></span>
                                        </div>
                                        <div class="num-item">
                                            <span class="num-valor"><?= $r['faltas'] ?></span>
                                            <span class="num-label"><?= __('label_faltas') ?></span>
                                        </div>
                                        <div class="num-item">
                                            <span class="num-valor"><?= $r['encaixes'] ?></span>
                                            <span class="num-label"><?= __('label_encaixes') ?></span>
                                        </div>
                                        <?php if ($r['tempo_medio_consulta']): ?>
                                        <div class="num-item">
                                            <span class="num-valor"><?= $r['tempo_medio_consulta'] ?>min</span>
                                            <span class="num-label"><?= __('label_tempo_medio') ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($r['observacao']): ?>
                                        <div class="registro-obs">
                                            📌 <?= htmlspecialchars($r['observacao']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="registro-acoes">

                                        <!-- EDITAR -->
                                        <a href="?id_agenda=<?= $r['id_agenda'] ?>"
                                           class="btn-mini btn-info">
                                            ✏️ <?= __('editar') ?>
                                        </a>

                                        <!-- EXCLUIR -->
                                        <form method="POST"
                                              onsubmit="return confirm('<?= __('confirmar_remover_registro') ?>')">
                                            <input type="hidden" name="csrf_token" 
                                                value="<?= gerar_csrf_token() ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id_registro"
                                                   value="<?= $r['id_registro'] ?>">
                                            <button class="btn-mini btn-danger">🗑 <?= __('remover') ?></button>
                                        </form>

                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="vazio">
                                <?= __('nenhum_registro_lancado') ?><br>
                                <small><?= __('use_formulario_lado') ?></small>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>

<script>
// preenche hora real com os horários do turno selecionado
function preencherHorarios(select) {
    const opt = select.options[select.selectedIndex];
    const inicio = opt.getAttribute('data-inicio');
    const fim    = opt.getAttribute('data-fim');

    if (inicio) {
        document.getElementById('hora_real_inicio').value = inicio.substring(0, 5);
    }
    if (fim) {
        document.getElementById('hora_real_fim').value = fim.substring(0, 5);
    }
}
</script>

</body>
</html>