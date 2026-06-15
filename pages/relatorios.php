<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

verificar_sessao();

$pdo = conectar();
$id_usuario = $_SESSION['id_usuario'];

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
$fim_mes = date('Y-m-t', strtotime($inicio_mes));

$stmt = $pdo->prepare("
    SELECT
        COUNT(r.id_registro) AS total_turnos,
        SUM(r.pacientes_atendidos) AS total_atendidos,
        SUM(r.faltas) AS total_faltas,
        SUM(r.cancelamentos) AS total_cancelamentos,
        SUM(r.encaixes) AS total_encaixes,
        AVG(r.pacientes_atendidos) AS media_por_turno,
        AVG(r.tempo_medio_consulta) AS media_tempo,
        SUM(r.duracao_real_minutos) AS total_minutos
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$resumo = $stmt->fetch();

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

$stmt = $pdo->prepare("
    SELECT l.nome, l.cor_identificacao,
           COUNT(r.id_registro) AS total_turnos,
           SUM(r.pacientes_atendidos) AS total_atendidos,
           SUM(r.faltas) AS total_faltas,
           AVG(r.pacientes_atendidos) AS media_turno
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho l ON l.id_local = a.id_local
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    GROUP BY l.id_local
    ORDER BY total_atendidos DESC
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$por_local = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(a.data_trabalho) AS dia_semana,
           SUM(r.pacientes_atendidos) AS total,
           COUNT(r.id_registro) AS turnos
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(a.data_trabalho)
    ORDER BY dia_semana ASC
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$por_dia_semana_raw = $stmt->fetchAll();

$dias_semana_label = [1=>'Dom',2=>'Seg',3=>'Ter',4=>'Qua',5=>'Qui',6=>'Sex',7=>'Sáb'];
$por_dia_semana = [];
foreach ($dias_semana_label as $num => $label) {
    $por_dia_semana[$num] = ['label' => $label, 'total' => 0, 'turnos' => 0];
}
foreach ($por_dia_semana_raw as $d) {
    $por_dia_semana[$d['dia_semana']]['total'] = (int)$d['total'];
    $por_dia_semana[$d['dia_semana']]['turnos'] = (int)$d['turnos'];
}

$stmt = $pdo->prepare("
    SELECT YEAR(a.data_trabalho) AS ano,
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

$total_atendidos = (int)($resumo['total_atendidos'] ?? 0);
$total_faltas = (int)($resumo['total_faltas'] ?? 0);
$total_agendados = $total_atendidos + $total_faltas;
$taxa_falta = $total_agendados > 0 ? round(($total_faltas / $total_agendados) * 100, 1) : 0;

$labels_dia = [];
$dados_dia = [];
$max_dia_mes = (int)date('t', strtotime($inicio_mes));
$mapa_dia = [];
foreach ($por_dia as $d) {
    $mapa_dia[$d['data_trabalho']] = (int)$d['total'];
}
for ($d = 1; $d <= $max_dia_mes; $d++) {
    $chave = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
    $labels_dia[] = (string)$d;
    $dados_dia[] = $mapa_dia[$chave] ?? 0;
}

$labels_semana = [];
$dados_semana = [];
foreach ($por_dia_semana as $item) {
    $labels_semana[] = $item['label'];
    $dados_semana[] = $item['total'];
}

$labels_local = [];
$dados_local = [];
$cores_local = [];
foreach ($por_local as $loc) {
    $labels_local[] = $loc['nome'];
    $dados_local[] = (int)$loc['total_atendidos'];
    $cores_local[] = $loc['cor_identificacao'] ?: '#2A9D8F';
}

$labels_evolucao = [];
$dados_evolucao = [];
foreach ($evolucao as $ev) {
    $labels_evolucao[] = substr($meses_pt[(int)$ev['mes']], 0, 3) . '/' . $ev['ano'];
    $dados_evolucao[] = (int)$ev['total'];
}

$titulo_pagina = 'Relatórios';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="layout">

<?php include '../includes/sidebar.php'; ?>

<div class="conteudo">
    <?php include '../includes/header.php'; ?>

    <main class="main">
        
        <div class="card">
            <div class="card-body relatorios-nav">
                <a href="?mes=<?= $mes-1 ?>&ano=<?= $ano ?>" class="btn-secondary">‹ Mês anterior</a>
                <h2 class="relatorios-periodo">📊 <?= $meses_pt[$mes] ?> <?= $ano ?></h2>
                <a href="?mes=<?= $mes+1 ?>&ano=<?= $ano ?>" class="btn-secondary">Próximo mês ›</a>
            </div>
        </div>

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

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h3>📅 Atendimentos por dia — <?= $meses_pt[$mes] ?></h3>
                </div>
                <div class="card-body">
                    <div class="chart-box chart-box-sm">
                        <canvas id="chartDia"></canvas>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>📆 Por dia da semana</h3>
                </div>
                <div class="card-body">
                    <div class="chart-box chart-box-sm">
                        <canvas id="chartSemana"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>📍 Por local de trabalho</h3>
            </div>
            <div class="card-body">
                <div class="chart-box chart-box-lg">
                    <canvas id="chartLocal"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>📈 Evolução dos últimos 6 meses</h3>
            </div>
            <div class="card-body">
                <div class="chart-box chart-box-sm">
                    <canvas id="chartEvolucao"></canvas>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</div>

<script>
const labelsDia = <?= json_encode($labels_dia, JSON_UNESCAPED_UNICODE) ?>;
const dadosDia = <?= json_encode($dados_dia, JSON_UNESCAPED_UNICODE) ?>;
const labelsSemana = <?= json_encode($labels_semana, JSON_UNESCAPED_UNICODE) ?>;
const dadosSemana = <?= json_encode($dados_semana, JSON_UNESCAPED_UNICODE) ?>;
const labelsLocal = <?= json_encode($labels_local, JSON_UNESCAPED_UNICODE) ?>;
const dadosLocal = <?= json_encode($dados_local, JSON_UNESCAPED_UNICODE) ?>;
const coresLocal = <?= json_encode($cores_local, JSON_UNESCAPED_UNICODE) ?>;
const labelsEvolucao = <?= json_encode($labels_evolucao, JSON_UNESCAPED_UNICODE) ?>;
const dadosEvolucao = <?= json_encode($dados_evolucao, JSON_UNESCAPED_UNICODE) ?>;

const common = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1f3440',
            titleColor: '#fff',
            bodyColor: '#fff',
            padding: 12
        }
    }
};

new Chart(document.getElementById('chartDia'), {
    type: 'bar',
    data: {
        labels: labelsDia,
        datasets: [{
            label: 'Atendimentos',
            data: dadosDia,
            backgroundColor: '#2A9D8F',
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        ...common,
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 0, autoSkip: true, color: '#6D7F88' }
            },
            y: {
                beginAtZero: true,
                grid: { color: '#EAF4F2' },
                ticks: { color: '#6D7F88' }
            }
        }
    }
});

new Chart(document.getElementById('chartSemana'), {
    type: 'bar',
    data: {
        labels: labelsSemana,
        datasets: [{
            label: 'Atendimentos',
            data: dadosSemana,
            backgroundColor: '#3A7BD5',
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        ...common,
        scales: {
            x: { grid: { display: false }, ticks: { color: '#6D7F88' } },
            y: {
                beginAtZero: true,
                grid: { color: '#EAF4F2' },
                ticks: { color: '#6D7F88' }
            }
        }
    }
});

new Chart(document.getElementById('chartLocal'), {
    type: 'bar',
    data: {
        labels: labelsLocal,
        datasets: [{
            label: 'Atendimentos',
            data: dadosLocal,
            backgroundColor: coresLocal,
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        ...common,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                grid: { color: '#EAF4F2' },
                ticks: { color: '#6D7F88' }
            },
            y: {
                grid: { display: false },
                ticks: { color: '#6D7F88' }
            }
        }
    }
});

new Chart(document.getElementById('chartEvolucao'), {
    type: 'line',
    data: {
        labels: labelsEvolucao,
        datasets: [{
            label: 'Atendimentos',
            data: dadosEvolucao,
            borderColor: '#2A9D8F',
            backgroundColor: 'rgba(42,157,143,0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#2A9D8F',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        ...common,
        scales: {
            x: { grid: { display: false }, ticks: { color: '#6D7F88' } },
            y: {
                beginAtZero: true,
                grid: { color: '#EAF4F2' },
                ticks: { color: '#6D7F88' }
            }
        }
    }
});
</script>
</body>
</html>