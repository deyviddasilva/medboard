<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

verificar_sessao();

$pdo        = conectar();
$id_usuario = $_SESSION['id_usuario'];

// -----------------------------------------------
// FILTROS
// -----------------------------------------------
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

if ($mes < 1)  { $mes = 12; $ano--; }
if ($mes > 12) { $mes = 1;  $ano++; }

$meses_pt = [
    1  => 'Janeiro',   2  => 'Fevereiro', 3  => 'Março',
    4  => 'Abril',     5  => 'Maio',      6  => 'Junho',
    7  => 'Julho',     8  => 'Agosto',    9  => 'Setembro',
    10 => 'Outubro',   11 => 'Novembro',  12 => 'Dezembro'
];

$inicio_mes = sprintf('%04d-%02d-01', $ano, $mes);
$fim_mes    = date('Y-m-t', strtotime($inicio_mes));

// -----------------------------------------------
// QUERY 1: RESUMO GERAL DO MÊS
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        COUNT(r.id_registro)         AS total_turnos,
        SUM(r.pacientes_atendidos)   AS total_atendidos,
        SUM(r.faltas)                AS total_faltas,
        SUM(r.cancelamentos)         AS total_cancelamentos,
        SUM(r.encaixes)              AS total_encaixes,
        AVG(r.pacientes_atendidos)   AS media_por_turno,
        AVG(r.tempo_medio_consulta)  AS media_tempo,
        SUM(r.duracao_real_minutos)  AS total_minutos
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$resumo = $stmt->fetch();

// -----------------------------------------------
// QUERY 2: ATENDIMENTOS POR DIA (gráfico)
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
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$por_dia = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 3: ATENDIMENTOS POR LOCAL
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT l.nome, l.cor_identificacao,
           COUNT(r.id_registro)       AS total_turnos,
           SUM(r.pacientes_atendidos) AS total_atendidos,
           SUM(r.faltas)              AS total_faltas,
           AVG(r.pacientes_atendidos) AS media_turno
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho  l ON l.id_local  = a.id_local
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    GROUP BY l.id_local
    ORDER BY total_atendidos DESC
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$por_local = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 4: ATENDIMENTOS POR DIA DA SEMANA
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(a.data_trabalho) AS dia_semana,
           SUM(r.pacientes_atendidos) AS total,
           COUNT(r.id_registro)       AS turnos
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(a.data_trabalho)
    ORDER BY dia_semana ASC
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$por_dia_semana_raw = $stmt->fetchAll();

// organiza por dia da semana (1=Dom ... 7=Sab)
$dias_semana_label = [1=>'Dom',2=>'Seg',3=>'Ter',4=>'Qua',5=>'Qui',6=>'Sex',7=>'Sáb'];
$por_dia_semana = [];
foreach ($dias_semana_label as $num => $label) {
    $por_dia_semana[$num] = ['label' => $label, 'total' => 0, 'turnos' => 0];
}
foreach ($por_dia_semana_raw as $d) {
    $por_dia_semana[$d['dia_semana']]['total']  = (int)$d['total'];
    $por_dia_semana[$d['dia_semana']]['turnos'] = (int)$d['turnos'];
}

// -----------------------------------------------
// QUERY 5: EVOLUÇÃO DOS ÚLTIMOS 6 MESES
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT YEAR(a.data_trabalho)  AS ano,
           MONTH(a.data_trabalho) AS mes,
           SUM(r.pacientes_atendidos) AS total
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY YEAR(a.data_trabalho), MONTH(a.data_trabalho)
    ORDER BY ano ASC, mes ASC
");
$stmt->execute([$id_usuario, $inicio_mes]);
$evolucao = $stmt->fetchAll();

// taxa de falta
$total_atendidos  = (int)($resumo['total_atendidos'] ?? 0);
$total_faltas     = (int)($resumo['total_faltas'] ?? 0);
$total_agendados  = $total_atendidos + $total_faltas;
$taxa_falta       = $total_agendados > 0
    ? round(($total_faltas / $total_agendados) * 100, 1)
    : 0;

$titulo_pagina = 'Relatórios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="layout">

<?php include '../includes/sidebar.php'; ?>

<div class="conteudo">

    <?php include '../includes/header.php'; ?>

    <main class="main">

        <!-- NAVEGAÇÃO DE MÊS -->
        <div class="card">
            <div class="card-body" style="flex-direction:row; align-items:center; gap:16px;">
                <a href="?mes=<?= $mes-1 ?>&ano=<?= $ano ?>" class="btn-secondary">‹ Mês anterior</a>
                <h2 style="flex:1; text-align:center; font-size:18px; color:#0f172a;">
                    📊 <?= $meses_pt[$mes] ?> <?= $ano ?>
                </h2>
                <a href="?mes=<?= $mes+1 ?>&ano=<?= $ano ?>" class="btn-secondary">Próximo mês ›</a>
            </div>
        </div>

        <!-- KPIs DO MÊS -->
        <section class="kpis">

            <div class="card-kpi">
                <div class="kpi-icone">👥</div>
                <div class="kpi-info">
                    <span class="kpi-label">Total atendidos</span>
                    <span class="kpi-valor"><?= $total_atendidos ?></span>
                    <span class="kpi-sub"><?= $resumo['total_turnos'] ?? 0 ?> turno(s)</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">📈</div>
                <div class="kpi-info">
                    <span class="kpi-label">Média por turno</span>
                    <span class="kpi-valor"><?= round($resumo['media_por_turno'] ?? 0, 1) ?></span>
                    <span class="kpi-sub">pacientes/turno</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">❌</div>
                <div class="kpi-info">
                    <span class="kpi-label">Taxa de falta</span>
                    <span class="kpi-valor"><?= $taxa_falta ?>%</span>
                    <span class="kpi-sub"><?= $total_faltas ?> falta(s)</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">➕</div>
                <div class="kpi-info">
                    <span class="kpi-label">Encaixes</span>
                    <span class="kpi-valor"><?= $resumo['total_encaixes'] ?? 0 ?></span>
                    <span class="kpi-sub">no mês</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">⏱</div>
                <div class="kpi-info">
                    <span class="kpi-label">Tempo médio</span>
                    <span class="kpi-valor">
                        <?= $resumo['media_tempo'] ? round($resumo['media_tempo']) . ' min' : '—' ?>
                    </span>
                    <span class="kpi-sub">por consulta</span>
                </div>
            </div>

        </section>

        <!-- GRÁFICO: ATENDIMENTOS POR DIA -->
        <div class="card">
            <div class="card-header">
                <h3>📅 Atendimentos por dia — <?= $meses_pt[$mes] ?></h3>
            </div>
            <div class="card-body">
                <?php if ($por_dia): ?>
                    <?php
                    $max_dia = max(array_column($por_dia, 'total'));
                    $mapa_dia = [];
                    foreach ($por_dia as $d) {
                        $mapa_dia[$d['data_trabalho']] = (int)$d['total'];
                    }
                    $dias_no_mes = (int)date('t', strtotime($inicio_mes));
                    ?>
                    <div class="grafico-barras grafico-mensal">
                        <?php for ($d = 1; $d <= $dias_no_mes; $d++):
                            $chave  = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
                            $valor  = $mapa_dia[$chave] ?? 0;
                            $altura = $valor > 0 ? round(($valor / $max_dia) * 100) : 2;
                            $e_hoje = $chave === date('Y-m-d');
                        ?>
                            <div class="barra-grupo">
                                <div class="barra-valor"><?= $valor > 0 ? $valor : '' ?></div>
                                <div class="barra <?= $e_hoje ? 'barra-hoje' : '' ?>"
                                     style="height:<?= $altura ?>%"></div>
                                <div class="barra-label"><?= $d ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <div class="vazio">Nenhum registro neste mês ainda.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2">

            <!-- ATENDIMENTOS POR LOCAL -->
            <div class="card">
                <div class="card-header">
                    <h3>📍 Por local de trabalho</h3>
                </div>
                <div class="card-body">
                    <?php if ($por_local): ?>
                        <?php
                        $max_local = max(array_column($por_local, 'total_atendidos'));
                        ?>
                        <?php foreach ($por_local as $loc): ?>
                            <div class="local-rel-item">
                                <div class="local-rel-topo">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="legenda-cor"
                                              style="background:<?= $loc['cor_identificacao'] ?>; width:12px; height:12px;">
                                        </span>
                                        <strong><?= htmlspecialchars($loc['nome']) ?></strong>
                                    </div>
                                    <span><?= $loc['total_atendidos'] ?> pac.</span>
                                </div>
                                <div class="barra-progresso">
                                    <div class="barra-progresso-fill"
                                         style="width:<?= round(($loc['total_atendidos'] / $max_local) * 100) ?>%;
                                                background:<?= $loc['cor_identificacao'] ?>">
                                    </div>
                                </div>
                                <div class="local-rel-sub">
                                    <?= $loc['total_turnos'] ?> turno(s) •
                                    Média: <?= round($loc['media_turno'], 1) ?>/turno •
                                    Faltas: <?= $loc['total_faltas'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="vazio">Sem dados neste mês.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ATENDIMENTOS POR DIA DA SEMANA -->
            <div class="card">
                <div class="card-header">
                    <h3>📆 Por dia da semana</h3>
                </div>
                <div class="card-body">
                    <?php
                    $max_semana = max(array_column($por_dia_semana, 'total') ?: [1]);
                    ?>
                    <div class="grafico-barras" style="height:160px;">
                        <?php foreach ($por_dia_semana as $num => $ds): ?>
                            <?php
                            $altura = $ds['total'] > 0
                                ? round(($ds['total'] / $max_semana) * 100)
                                : 2;
                            ?>
                            <div class="barra-grupo">
                                <div class="barra-valor">
                                    <?= $ds['total'] > 0 ? $ds['total'] : '' ?>
                                </div>
                                <div class="barra <?= $num == date('w')+1 ? 'barra-hoje' : '' ?>"
                                     style="height:<?= $altura ?>%">
                                </div>
                                <div class="barra-label"><?= $ds['label'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- EVOLUÇÃO 6 MESES -->
        <div class="card">
            <div class="card-header">
                <h3>📈 Evolução dos últimos 6 meses</h3>
            </div>
            <div class="card-body">
                <?php if ($evolucao): ?>
                    <?php $max_evol = max(array_column($evolucao, 'total') ?: [1]); ?>
                    <div class="grafico-barras" style="height:160px;">
                        <?php foreach ($evolucao as $ev):
                            $altura = round(($ev['total'] / $max_evol) * 100);
                            $atual  = ($ev['mes'] == $mes && $ev['ano'] == $ano);
                        ?>
                            <div class="barra-grupo">
                                <div class="barra-valor"><?= $ev['total'] ?></div>
                                <div class="barra <?= $atual ? 'barra-hoje' : '' ?>"
                                     style="height:<?= $altura ?>%">
                                </div>
                                <div class="barra-label">
                                    <?= substr($meses_pt[(int)$ev['mes']], 0, 3) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="vazio">Ainda não há dados suficientes para evolução.</div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>
</body>
</html>