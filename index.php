<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';
require_once 'includes/i18n.php';

verificar_sessao();

$pdo        = conectar();
$id_usuario = $_SESSION['id_usuario'];
$hoje       = date('Y-m-d');
$agora      = date('H:i:s');

// -----------------------------------------------
// QUERY 1: turnos de hoje
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.id_agenda, a.turno, a.hora_inicio, a.hora_fim,
           a.status_agenda, a.observacao, a.data_fim,
           l.nome AS local_nome, l.cor_identificacao
    FROM agenda_trabalho a
    JOIN locais_trabalho l ON l.id_local = a.id_local
    WHERE a.id_usuario = ? AND a.data_trabalho = ?
    ORDER BY a.hora_inicio ASC
");
$stmt->execute([$id_usuario, $hoje]);
$turnos_hoje = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 2: resumo de hoje (registros lançados)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        SUM(r.pacientes_atendidos) AS total_atendidos,
        SUM(r.faltas)              AS total_faltas,
        SUM(r.cancelamentos)       AS total_cancelamentos,
        SUM(r.encaixes)            AS total_encaixes
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ? AND a.data_trabalho = ?
");
$stmt->execute([$id_usuario, $hoje]);
$resumo_hoje = $stmt->fetch();

// -----------------------------------------------
// QUERY 3: total da semana
// -----------------------------------------------
$inicio_semana = date('Y-m-d', strtotime('monday this week'));
$fim_semana    = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT
        SUM(r.pacientes_atendidos)      AS total_semana,
        COUNT(DISTINCT a.data_trabalho) AS dias_trabalhados
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
");
$stmt->execute([$id_usuario, $inicio_semana, $fim_semana]);
$resumo_semana = $stmt->fetch();

// -----------------------------------------------
// QUERY 4: próximos plantões (próximos 7 dias)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.id_agenda, a.data_trabalho, a.turno,
           a.hora_inicio, a.hora_fim, a.observacao,
           l.nome AS local_nome, l.cor_identificacao
    FROM agenda_trabalho a
    JOIN locais_trabalho l ON l.id_local = a.id_local
    WHERE a.id_usuario = ?
      AND a.data_trabalho > ?
      AND a.status_agenda = 'agendado'
    ORDER BY a.data_trabalho ASC, a.hora_inicio ASC
    LIMIT 5
");
$stmt->execute([$id_usuario, $hoje]);
$proximos_plantoes = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 5: gráfico da semana
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.data_trabalho,
           SUM(r.pacientes_atendidos) AS total
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    GROUP BY a.data_trabalho
    ORDER BY a.data_trabalho ASC
");
$stmt->execute([$id_usuario, $inicio_semana, $fim_semana]);
$dados_grafico = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 6: turnos de hoje SEM registro (para ação rápida)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.id_agenda
    FROM agenda_trabalho a
    LEFT JOIN registro_diario r ON r.id_agenda = a.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho = ?
      AND r.id_registro IS NULL
      AND a.status_agenda NOT IN ('cancelado', 'folga')
");
$stmt->execute([$id_usuario, $hoje]);
$turnos_sem_registro = $stmt->fetchAll();
$pendentes_hoje = count($turnos_sem_registro);

// -----------------------------------------------
// QUERY 7: lembretes pendentes de hoje
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM lembretes
    WHERE id_usuario = ?
      AND data_lembrete = ?
      AND status_lembrete = 'pendente'
    ORDER BY hora_lembrete ASC
");
$stmt->execute([$id_usuario, $hoje]);
$lembretes_hoje = $stmt->fetchAll();

// próximo turno pendente
$proximo_turno = null;
foreach ($turnos_hoje as $t) {
    if ($t['hora_inicio'] >= $agora && $t['status_agenda'] === 'agendado') {
        $proximo_turno = $t;
        break;
    }
}

// dias da semana em pt
$dias_semana_pt = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$meses_pt = [
    1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr',
    5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago',
    9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'
];

$titulo_pagina = __('home');
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
    <title><?= __('home') ?> — MedBoard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="layout">
<script>
if (localStorage.getItem('medboard-tema') === 'dark') {
    document.body.classList.add('dark');
}
</script>

<?php include 'includes/sidebar.php'; ?>

<div class="conteudo">

    <?php include 'includes/header.php'; ?>

    <main class="main">

        <!-- LEMBRETES DO DIA -->
        <?php if ($lembretes_hoje): ?>
            <div class="alerta alerta-lembrete">
                <?= __('lembretes_hoje') ?>
                <?php foreach ($lembretes_hoje as $lem): ?>
                    <?= htmlspecialchars($lem['titulo']) ?>
                    <?= $lem['hora_lembrete'] ? '(' . date('H:i', strtotime($lem['hora_lembrete'])) . ')' : '' ?>
                    &nbsp;
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- AVISO DE REGISTRO PENDENTE -->
        <?php if ($pendentes_hoje > 0): ?>
            <div class="alerta alerta-aviso">
                ⚠️ <strong><?= $pendentes_hoje ?> <?= __('turnos_sem_registro') ?></strong>
                <a href="pages/registro_dia.php" class="link-alerta"><?= __('lancar_agora') ?></a>
            </div>
        <?php endif; ?>

        <!-- BLOCO 1: KPIs -->
        <section class="kpis">

            <div class="card-kpi">
                <div class="kpi-icone">📍</div>
                <div class="kpi-info">
                    <span class="kpi-label"><?= __('trabalho_hoje_em') ?></span>
                    <?php if ($turnos_hoje): ?>
                        <?php foreach ($turnos_hoje as $t): ?>
                            <span class="kpi-valor"
                                  style="border-left:3px solid <?= $t['cor_identificacao'] ?>;
                                         padding-left:8px; margin-bottom:2px;">
                                <?= htmlspecialchars($t['local_nome']) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="kpi-valor"><?= __('folga_hoje') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">🕐</div>
                <div class="kpi-info">
                    <span class="kpi-label"><?= __('proximo_turno') ?></span>
                    <?php if ($proximo_turno): ?>
                        <span class="kpi-valor">
                            <?= date('H:i', strtotime($proximo_turno['hora_inicio'])) ?>
                        </span>
                        <span class="kpi-sub">
                            <?= htmlspecialchars($proximo_turno['local_nome']) ?>
                        </span>
                    <?php else: ?>
                        <span class="kpi-valor">—</span>
                        <span class="kpi-sub"><?= __('nenhum_turno_pendente') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">👥</div>
                <div class="kpi-info">
                    <span class="kpi-label"><?= __('atendidos_hoje') ?></span>
                    <span class="kpi-valor">
                        <?= $resumo_hoje['total_atendidos'] ?? 0 ?> <?= __('pacientes') ?>
                    </span>
                    <?php if (($resumo_hoje['total_encaixes'] ?? 0) > 0): ?>
                        <span class="kpi-sub">
                            +<?= $resumo_hoje['total_encaixes'] ?> <?= __('encaixe') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">❌</div>
                <div class="kpi-info">
                    <span class="kpi-label"><?= __('faltas_hoje') ?></span>
                    <span class="kpi-valor">
                        <?= $resumo_hoje['total_faltas'] ?? 0 ?> <?= __('paciente_singular') ?>
                    </span>
                    <?php if (($resumo_hoje['total_cancelamentos'] ?? 0) > 0): ?>
                        <span class="kpi-sub">
                            <?= $resumo_hoje['total_cancelamentos'] ?> <?= __('cancelamento') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">📈</div>
                <div class="kpi-info">
                    <span class="kpi-label"><?= __('total_semana') ?></span>
                    <span class="kpi-valor">
                        <?= $resumo_semana['total_semana'] ?? 0 ?> <?= __('pacientes') ?>
                    </span>
                    <span class="kpi-sub">
                        <?= $resumo_semana['dias_trabalhados'] ?? 0 ?> <?= __('dias_lancados') ?>
                    </span>
                </div>
            </div>

        </section>

        <!-- BLOCO 2: AGENDA DE HOJE + PRÓXIMOS PLANTÕES -->
        <section class="grid-2">

            <div class="card">
                <div class="card-header">
                    <h3>📅 <?= __('agenda_de_hoje') ?></h3>
                    <a href="pages/agenda.php" class="link-ver-mais"><?= __('ver_agenda') ?></a>
                </div>
                <div class="card-body">
                    <?php if ($turnos_hoje): ?>
                        <?php foreach ($turnos_hoje as $t): ?>
                            <div class="turno-item"
                                 style="border-left:4px solid <?= $t['cor_identificacao'] ?>">
                                <div class="turno-horario">
                                    <?= date('H:i', strtotime($t['hora_inicio'])) ?>
                                    –
                                    <?= date('H:i', strtotime($t['hora_fim'])) ?>
                                    <?php if (!empty($t['data_fim'])): ?>
                                        <span class="badge-virada">+1 dia</span>
                                    <?php endif; ?>
                                </div>
                                <div class="turno-info">
                                    <strong><?= htmlspecialchars($t['local_nome']) ?></strong>
                                    <span>
                                        <?= ucfirst(str_replace('/', ' e ', $t['turno'])) ?>
                                    </span>
                                    <?php if ($t['observacao']): ?>
                                        <small>
                                            📌 <?= htmlspecialchars($t['observacao']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                                    <span class="badge badge-<?= $t['status_agenda'] ?>">
                                        <?= __($t['status_agenda']) ?>
                                    </span>
                                    <a href="pages/registro_dia.php?id_agenda=<?= $t['id_agenda'] ?>"
                                       class="btn-mini btn-success">
                                        <?= __('registrar') ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="vazio">
                            <?= __('nenhum_turno_hoje') ?><br>
                            <small>
                                <a href="pages/agenda.php"><?= __('adicionar_turno') ?></a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>🗓️ <?= __('proximos_plantoes') ?></h3>
                    <a href="pages/agenda.php" class="link-ver-mais"><?= __('ver_todos') ?></a>
                </div>
                <div class="card-body">
                    <?php if ($proximos_plantoes): ?>
                        <?php foreach ($proximos_plantoes as $p): ?>
                            <div class="turno-item"
                                 style="border-left:4px solid <?= $p['cor_identificacao'] ?>">
                                <div class="turno-horario">
                                    <?= date('d/m', strtotime($p['data_trabalho'])) ?>
                                    <small>
                                        <?= $dias_semana_pt[date('w', strtotime($p['data_trabalho']))] ?>
                                    </small>
                                </div>
                                <div class="turno-info">
                                    <strong><?= htmlspecialchars($p['local_nome']) ?></strong>
                                    <span>
                                        <?= date('H:i', strtotime($p['hora_inicio'])) ?>
                                        –
                                        <?= date('H:i', strtotime($p['hora_fim'])) ?>
                                    </span>
                                    <?php if ($p['observacao']): ?>
                                        <small>📌 <?= htmlspecialchars($p['observacao']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="vazio">
                            <?= __('nenhum_plantao') ?><br>
                            <small>
                                <a href="pages/agenda.php"><?= __('adicionar_plantao_link') ?></a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </section>

        <!-- BLOCO 3: GRÁFICO DA SEMANA -->
        <div class="card">
            <div class="card-header">
                <h3>📊 <?= __('atendimentos_semana') ?></h3>
                <a href="pages/relatorios.php" class="link-ver-mais"><?= __('ver_relatorio') ?></a>
            </div>
            <div class="card-body">
                <div class="grafico-barras">
                    <?php
                    $dias_label = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
                    $mapa_grafico = [];
                    foreach ($dados_grafico as $dg) {
                        $mapa_grafico[$dg['data_trabalho']] = (int)$dg['total'];
                    }
                    $max_grafico = max(array_merge([1], array_values($mapa_grafico)));

                    for ($i = 0; $i < 7; $i++):
                        $data_g = date('Y-m-d', strtotime("monday this week +{$i} days"));
                        $valor  = $mapa_grafico[$data_g] ?? 0;
                        $altura = $valor > 0 ? round(($valor / $max_grafico) * 100) : 4;
                        $e_hoje = $data_g === $hoje;
                    ?>
                        <div class="barra-grupo">
                            <div class="barra-valor"><?= $valor > 0 ? $valor : '' ?></div>
                            <div class="barra <?= $e_hoje ? 'barra-hoje' : '' ?>"
                                 style="height:<?= $altura ?>%">
                            </div>
                            <div class="barra-label"><?= $dias_label[$i] ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- BLOCO 4: AÇÕES RÁPIDAS -->
        <section class="acoes-rapidas">
            <a href="pages/registro_dia.php" class="btn-primary">
                <?= __('registrar_pacientes') ?>
            </a>
            <a href="pages/agenda.php" class="btn-secondary">
                <?= __('adicionar_plantao') ?>
            </a>
            <a href="pages/relatorios.php" class="btn-secondary">
                <?= __('ver_relatorio_btn') ?>
            </a>
            <a href="pages/locais.php" class="btn-secondary">
                <?= __('gerenciar_locais') ?>
            </a>
        </section>

    </main>

    <?php include 'includes/footer.php'; ?>

</div>
</body>
</html>