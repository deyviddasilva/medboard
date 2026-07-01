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

// mês e ano atual ou navegado
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

// ajuste de limites
if ($mes < 1)  { $mes = 12; $ano--; }
if ($mes > 12) { $mes = 1;  $ano++; }

// -----------------------------------------------
// AÇÃO: CADASTRAR TURNO
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {

        $data        = $_POST['data_trabalho'] ?? '';
        $id_local    = (int)($_POST['id_local'] ?? 0);
        $turno       = $_POST['turno'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fim    = $_POST['hora_fim'] ?? '';
        $status      = $_POST['status_agenda'] ?? 'agendado';
        $obs         = trim($_POST['observacao'] ?? '');

        if (empty($data) || $id_local === 0 || empty($turno) || empty($hora_inicio) || empty($hora_fim)) {

            $erro = __('erro_campos_obrigatorios');

        } else {

            // calcula data_fim para turnos que viram o dia
            $data_fim = null;

            if ($hora_fim <= $hora_inicio) {
                if (in_array($turno, ['noite', 'tarde/noite', 'manha/tarde/noite'])) {
                    $data_fim = date('Y-m-d', strtotime($data . ' +1 day'));
                } else {
                    $erro = __('erro_horario_fim_maior');
                }
            }

            if (empty($erro)) {

                // verifica duplicidade
                $stmt = $pdo->prepare("
                    SELECT id_agenda FROM agenda_trabalho
                    WHERE id_usuario = ? AND id_local = ? 
                      AND data_trabalho = ? AND hora_inicio = ?
                ");
                $stmt->execute([$id_usuario, $id_local, $data, $hora_inicio]);

                if ($stmt->fetch()) {
                    $erro = __('erro_turno_duplicado');
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO agenda_trabalho
                            (id_usuario, id_local, data_trabalho, data_fim, turno,
                             hora_inicio, hora_fim, status_agenda, observacao)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_usuario, $id_local, $data, $data_fim, $turno,
                        $hora_inicio, $hora_fim, $status, $obs ?: null
                    ]);
                    $sucesso = __('sucesso_turno_cadastrado');
                }
            }
        }
    }
}

// -----------------------------------------------
// AÇÃO: EXCLUIR TURNO
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id_agenda = (int)($_POST['id_agenda'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT id_agenda FROM agenda_trabalho
        WHERE id_agenda = ? AND id_usuario = ?
    ");
    $stmt->execute([$id_agenda, $id_usuario]);

    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM agenda_trabalho WHERE id_agenda = ?")
            ->execute([$id_agenda]);
        $sucesso = __('sucesso_turno_removido');
    }
}

// -----------------------------------------------
// AÇÃO: ATUALIZAR TURNO
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = __('erro_requisicao_invalida');
    } else {
        $id_agenda   = (int)($_POST['id_agenda']   ?? 0);
        $id_local    = (int)($_POST['id_local']    ?? 0);
        $turno       = $_POST['turno']             ?? '';
        $hora_inicio = $_POST['hora_inicio']       ?? '';
        $hora_fim    = $_POST['hora_fim']           ?? '';
        $status      = $_POST['status_agenda']     ?? 'agendado';
        $obs         = trim($_POST['observacao']   ?? '');

        if (empty($id_local) || empty($turno) || empty($hora_inicio) || empty($hora_fim)) {
            $erro = __('erro_campos_obrigatorios');
        } else {
            $data_fim = null;
            if ($hora_fim <= $hora_inicio) {
                if (in_array($turno, ['noite','tarde/noite','manha/tarde/noite'])) {
                    $stmt = $pdo->prepare("
                        SELECT data_trabalho FROM agenda_trabalho WHERE id_agenda = ?
                    ");
                    $stmt->execute([$id_agenda]);
                    $ag = $stmt->fetch();
                    $data_fim = date('Y-m-d', strtotime($ag['data_trabalho'] . ' +1 day'));
                } else {
                    $erro = __('erro_horario_fim_maior_curto');
                }
            }

            if (empty($erro)) {
                $stmt = $pdo->prepare("
                    SELECT id_agenda FROM agenda_trabalho
                    WHERE id_agenda = ? AND id_usuario = ?
                ");
                $stmt->execute([$id_agenda, $id_usuario]);

                if ($stmt->fetch()) {
                    $pdo->prepare("
                        UPDATE agenda_trabalho SET
                            id_local      = ?,
                            turno         = ?,
                            hora_inicio   = ?,
                            hora_fim      = ?,
                            data_fim      = ?,
                            status_agenda = ?,
                            observacao    = ?
                        WHERE id_agenda = ?
                    ")->execute([
                        $id_local, $turno, $hora_inicio,
                        $hora_fim, $data_fim, $status,
                        $obs ?: null, $id_agenda
                    ]);
                    $sucesso = __('sucesso_turno_atualizado');
                }
            }
        }
    }
}

// pré-carrega turno para edição
$turno_editar = null;
if (!empty($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT a.*, l.nome AS local_nome
        FROM agenda_trabalho a
        JOIN locais_trabalho l ON l.id_local = a.id_local
        WHERE a.id_agenda = ? AND a.id_usuario = ?
    ");
    $stmt->execute([(int)$_GET['editar'], $id_usuario]);
    $turno_editar = $stmt->fetch();
}

// -----------------------------------------------
// BUSCA: TURNOS DO MÊS
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.*, l.nome AS local_nome, l.cor_identificacao
    FROM agenda_trabalho a
    JOIN locais_trabalho l ON l.id_local = a.id_local
    WHERE a.id_usuario = ?
      AND MONTH(a.data_trabalho) = ?
      AND YEAR(a.data_trabalho)  = ?
    ORDER BY a.data_trabalho ASC, a.hora_inicio ASC
");
$stmt->execute([$id_usuario, $mes, $ano]);
$turnos = $stmt->fetchAll();

// organiza por data para o calendário
$turnos_por_dia = [];
foreach ($turnos as $t) {
    $turnos_por_dia[$t['data_trabalho']][] = $t;
}

// -----------------------------------------------
// BUSCA: LOCAIS ATIVOS
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT id_local, nome, cor_identificacao
    FROM locais_trabalho
    WHERE id_usuario = ? AND ativo = 1
    ORDER BY nome ASC
");
$stmt->execute([$id_usuario]);
$locais = $stmt->fetchAll();

// -----------------------------------------------
// DADOS DO CALENDÁRIO (meses e dias traduzidos)
// -----------------------------------------------
$meses_chave = [
    1 => 'mes_janeiro',   2 => 'mes_fevereiro', 3 => 'mes_marco',
    4 => 'mes_abril',     5 => 'mes_maio',      6 => 'mes_junho',
    7 => 'mes_julho',     8 => 'mes_agosto',    9 => 'mes_setembro',
    10 => 'mes_outubro',  11 => 'mes_novembro', 12 => 'mes_dezembro'
];

$dias_semana_chave = ['dia_dom','dia_seg','dia_ter','dia_qua','dia_qui','dia_sex','dia_sab'];

$primeiro_dia   = mktime(0, 0, 0, $mes, 1, $ano);
$dias_no_mes    = (int)date('t', $primeiro_dia);
$dia_semana_ini = (int)date('w', $primeiro_dia);
$nome_mes       = __($meses_chave[$mes]);

$titulo_pagina = __('agenda_mensal');

$turnos_opt = [
    'manha'             => __('turno_manha'),
    'tarde'             => __('turno_tarde'),
    'noite'             => __('turno_noite'),
    'manha/tarde'       => __('turno_manha_tarde'),
    'tarde/noite'       => __('turno_tarde_noite'),
    'manha/tarde/noite' => __('turno_dia_inteiro'),
];

$status_opt = [
    'agendado'  => __('agendado'),
    'concluido' => __('concluido'),
    'cancelado' => __('cancelado'),
    'folga'     => __('folga'),
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
    <title><?= __('agenda_mensal') ?> — MedBoard</title>
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

        <div class="agenda-layout">

            <!-- COLUNA ESQUERDA: CALENDÁRIO -->
            <div class="agenda-esquerda">
                <div class="card">
                    <div class="card-header">

                        <!-- NAVEGAÇÃO DO MÊS -->
                        <div class="mes-nav">
                            <a href="?mes=<?= $mes-1 ?>&ano=<?= $ano ?>" class="btn-mes">‹</a>
                            <h3><?= $nome_mes ?> <?= $ano ?></h3>
                            <a href="?mes=<?= $mes+1 ?>&ano=<?= $ano ?>" class="btn-mes">›</a>
                        </div>

                    </div>
                    <div class="card-body">

                        <!-- CALENDÁRIO -->
                        <div class="calendario">

                            <!-- cabeçalho dos dias -->
                            <?php foreach ($dias_semana_chave as $dchave): ?>
                                <div class="cal-cabecalho"><?= __($dchave) ?></div>
                            <?php endforeach; ?>

                            <!-- espaços vazios antes do dia 1 -->
                            <?php for ($i = 0; $i < $dia_semana_ini; $i++): ?>
                                <div class="cal-dia vazio"></div>
                            <?php endfor; ?>

                            <!-- dias do mês -->
                            <?php for ($dia = 1; $dia <= $dias_no_mes; $dia++):
                                $data_key  = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                                $e_hoje    = $data_key === date('Y-m-d');
                                $tem_turno = isset($turnos_por_dia[$data_key]);
                            ?>
                                <div class="cal-dia <?= $e_hoje ? 'hoje' : '' ?> <?= $tem_turno ? 'tem-turno' : '' ?>"
                                     onclick="selecionarDia('<?= $data_key ?>')"
                                     id="dia-<?= $data_key ?>">
                                    <span class="cal-numero"><?= $dia ?></span>
                                    <?php if ($tem_turno): ?>
                                        <div class="cal-pontos">
                                            <?php foreach ($turnos_por_dia[$data_key] as $t): ?>
                                                <span class="cal-ponto"
                                                      style="background:<?= $t['cor_identificacao'] ?>">
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>

                        </div>

                        <!-- LEGENDA DE LOCAIS -->
                        <?php if ($locais): ?>
                            <div class="legenda">
                                <?php foreach ($locais as $l): ?>
                                    <div class="legenda-item">
                                        <span class="legenda-cor"
                                              style="background:<?= $l['cor_identificacao'] ?>">
                                        </span>
                                        <span><?= htmlspecialchars($l['nome']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA: FORMULÁRIO + LISTA -->
            <div class="agenda-direita">

                <!-- FORMULÁRIO -->
                <div class="card" <?= $turno_editar ? 'style="border: 2px solid #3b82f6;"' : '' ?>>
                    <div class="card-header">
                        <h3><?= $turno_editar ? '✏️ ' . __('editar_turno') : '➕ ' . __('cadastrar_turno') ?></h3>
                        <?php if ($turno_editar): ?>
                            <a href="agenda.php?mes=<?= $mes ?>&ano=<?= $ano ?>"
                               class="btn-secondary">✕ <?= __('cancelar') ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="form-agenda">

                            <input type="hidden" name="csrf_token"
                                   value="<?= gerar_csrf_token() ?>">

                            <?php if ($turno_editar): ?>
                                <input type="hidden" name="acao" value="atualizar">
                                <input type="hidden" name="id_agenda"
                                       value="<?= $turno_editar['id_agenda'] ?>">
                            <?php else: ?>
                                <input type="hidden" name="acao" value="cadastrar">
                            <?php endif; ?>

                            <div class="campo">
                                <label><?= __('campo_data') ?> *</label>
                                <input type="date" name="data_trabalho"
                                       id="campo_data"
                                       value="<?= $turno_editar ? $turno_editar['data_trabalho'] : date('Y-m-d') ?>"
                                       <?= $turno_editar ? 'readonly' : "min=\"{$ano}-01-01\"" ?>
                                       required>
                            </div>

                            <div class="campo">
                                <label><?= __('campo_local') ?> *</label>
                                <select name="id_local" required>
                                 <option value=""><?= __('selecione') ?>...</option>
                                    <?php foreach ($locais as $l): ?>
                                        <option value="<?= $l['id_local'] ?>"
                                            <?= ($turno_editar && $turno_editar['id_local'] == $l['id_local'])
                                                ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($l['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo">
                                <label><?= __('campo_turno') ?> *</label>
                                <select name="turno" required>
                                    <option value=""><?= __('selecione') ?>...</option>
                                    <?php foreach ($turnos_opt as $val => $label): ?>
                                     <option value="<?= $val ?>"
                                         <?= ($turno_editar && $turno_editar['turno'] === $val)
                                                ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo">
                                <label><?= __('campo_status') ?></label>
                                <select name="status_agenda">
                                    <?php foreach ($status_opt as $val => $label): ?>
                                        <option value="<?= $val ?>"
                                            <?= ($turno_editar && $turno_editar['status_agenda'] === $val)
                                                ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="campo">
                                <label><?= __('campo_hora_inicio') ?> *</label>
                                <input type="time" name="hora_inicio" required
                                    value="<?= $turno_editar
                                           ? date('H:i', strtotime($turno_editar['hora_inicio']))
                                           : '' ?>">
                            </div>

                            <div class="campo">
                                <label><?= __('campo_hora_fim') ?> *</label>
                                <input type="time" name="hora_fim" required
                                    value="<?= $turno_editar
                                           ? date('H:i', strtotime($turno_editar['hora_fim']))
                                           : '' ?>">
                            </div>

                            <div class="campo campo-full">
                                <label><?= __('campo_observacao') ?></label>
                                <input type="text" name="observacao"
                                       placeholder="<?= __('placeholder_obs_turno') ?>"
                                       value="<?= htmlspecialchars($turno_editar['observacao'] ?? '') ?>">
                            </div>

                            <div class="campo campo-full">
                                <button type="submit" class="btn-primary btn-block">
                                    <?= $turno_editar ? '💾 ' . __('salvar_alteracoes') : '+ ' . __('cadastrar_turno') ?>
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- LISTA DE TURNOS DO MÊS -->
                <div class="card">
                    <div class="card-header">
                        <h3>📋 <?= __('turnos_de') ?> <?= $nome_mes ?></h3>
                        <span class="badge badge-agendado"><?= count($turnos) ?> <?= __('turno_s') ?></span>
                    </div>
                    <div class="card-body" id="lista-turnos">
                        <?php if ($turnos): ?>
                            <?php foreach ($turnos as $t): ?>
                                <div class="turno-item"
                                     style="border-left: 4px solid <?= $t['cor_identificacao'] ?>"
                                     id="turno-<?= $t['data_trabalho'] ?>">

                                    <div class="turno-horario">
                                        <?= date('d/m', strtotime($t['data_trabalho'])) ?>
                                        <small>
                                            <?= date('H:i', strtotime($t['hora_inicio'])) ?>
                                            –
                                            <?= date('H:i', strtotime($t['hora_fim'])) ?>
                                            <?php if (!empty($t['data_fim'])): ?>
                                                <span class="badge-virada" title="<?= __('termina_dia_seguinte') ?>">
                                                    <?= __('ate') ?> <?= date('d/m', strtotime($t['data_fim'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <div class="turno-info">
                                        <strong><?= htmlspecialchars($t['local_nome']) ?></strong>
                                        <span><?= $turnos_opt[$t['turno']] ?? ucfirst($t['turno']) ?></span>
                                        <?php if ($t['observacao']): ?>
                                            <small>📌 <?= htmlspecialchars($t['observacao']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                                        <span class="badge badge-<?= $t['status_agenda'] ?>">
                                            <?= $status_opt[$t['status_agenda']] ?? ucfirst($t['status_agenda']) ?>
                                        </span>

                                        <a href="?editar=<?= $t['id_agenda'] ?>&mes=<?= $mes ?>&ano=<?= $ano ?>"
                                           class="btn-mini btn-info">
                                            ✏️ <?= __('editar') ?>
                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('<?= __('confirmar_remover_turno') ?>')">
                                            <input type="hidden" name="csrf_token" 
                                                value="<?= gerar_csrf_token() ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id_agenda"
                                                   value="<?= $t['id_agenda'] ?>">
                                            <button class="btn-mini btn-danger">🗑</button>
                                        </form>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="vazio">
                                <?= __('nenhum_turno_no_mes') ?> <?= $nome_mes ?>.<br>
                                <small><?= __('use_formulario_adicionar') ?></small>
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
function selecionarDia(data) {
    document.getElementById('campo_data').value = data;
    document.getElementById('campo_data').scrollIntoView({ behavior: 'smooth' });
}
</script>

</body>
</html>