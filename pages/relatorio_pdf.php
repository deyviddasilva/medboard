<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

verificar_sessao();

$pdo        = conectar();
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
$fim_mes    = date('Y-m-t', strtotime($inicio_mes));

// -----------------------------------------------
// QUERY 1: KPIs do mês
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        COUNT(r.id_registro)        AS total_turnos,
        SUM(r.pacientes_atendidos)  AS total_atendidos,
        SUM(r.faltas)               AS total_faltas,
        SUM(r.cancelamentos)        AS total_cancelamentos,
        SUM(r.encaixes)             AS total_encaixes,
        AVG(r.pacientes_atendidos)  AS media_por_turno,
        AVG(r.tempo_medio_consulta) AS media_tempo
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$resumo = $stmt->fetch();

// -----------------------------------------------
// QUERY 2: Atendimentos do mês
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        r.pacientes_atendidos,
        r.faltas,
        r.cancelamentos,
        r.encaixes,
        r.tempo_medio_consulta,
        r.situacao_dia,
        r.observacao,
        a.data_trabalho,
        a.turno,
        a.hora_inicio,
        a.hora_fim,
        l.nome AS local_nome,
        l.cor_identificacao
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho  l ON l.id_local  = a.id_local
    WHERE a.id_usuario = ?
      AND a.data_trabalho BETWEEN ? AND ?
    ORDER BY a.data_trabalho ASC, a.hora_inicio ASC
");
$stmt->execute([$id_usuario, $inicio_mes, $fim_mes]);
$atendimentos = $stmt->fetchAll();

// -----------------------------------------------
// QUERY 3: Por local
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        l.nome,
        l.cor_identificacao,
        COUNT(r.id_registro)        AS total_turnos,
        SUM(r.pacientes_atendidos)  AS total_atendidos,
        SUM(r.faltas)               AS total_faltas,
        AVG(r.pacientes_atendidos)  AS media_turno
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

$total_atendidos = (int)($resumo['total_atendidos'] ?? 0);
$total_faltas    = (int)($resumo['total_faltas']    ?? 0);
$total_agendados = $total_atendidos + $total_faltas;
$taxa_falta      = $total_agendados > 0
    ? round(($total_faltas / $total_agendados) * 100, 1) : 0;

$situacoes = [
    'realizado'              => 'Realizado',
    'realizado_parcialmente' => 'Realizado parcialmente',
    'nao_realizado'          => 'Não realizado',
    'substituido'            => 'Substituído',
];

$dias_semana_pt = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
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
    <title>Relatório <?= $meses_pt[$mes] ?> <?= $ano ?> — MedBoard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            font-size: 13px;
            color: #1F3440;
            background: #fff;
            padding: 32px;
            line-height: 1.5;
        }

        /* -----------------------------------------------
           CABEÇALHO
        ----------------------------------------------- */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 2px solid #2A9D8F;
            margin-bottom: 24px;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-logo h1 {
            font-size: 22px;
            font-weight: 700;
            color: #2A9D8F;
            letter-spacing: -0.5px;
        }

        .header-logo span {
            font-size: 26px;
        }

        .header-info {
            text-align: right;
        }

        .header-info h2 {
            font-size: 16px;
            font-weight: 700;
            color: #1F3440;
        }

        .header-info p {
            font-size: 12px;
            color: #6D7F88;
            margin-top: 2px;
        }

        /* -----------------------------------------------
           SEÇÕES
        ----------------------------------------------- */
        .secao {
            margin-bottom: 28px;
        }

        .secao-titulo {
            font-size: 13px;
            font-weight: 700;
            color: #2A9D8F;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #D9E7E5;
        }

        /* -----------------------------------------------
           KPIs
        ----------------------------------------------- */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .kpi-card {
            background: #F4FAF9;
            border: 1px solid #D9E7E5;
            border-radius: 8px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .kpi-card.destaque {
            background: #2A9D8F;
            border-color: #2A9D8F;
        }

        .kpi-card.destaque .kpi-label,
        .kpi-card.destaque .kpi-valor {
            color: #fff;
        }

        .kpi-label {
            font-size: 10px;
            font-weight: 600;
            color: #6D7F88;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-valor {
            font-size: 24px;
            font-weight: 700;
            color: #1F3440;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .kpi-sub {
            font-size: 11px;
            color: #A0B4BC;
        }

        /* -----------------------------------------------
           TABELA POR LOCAL
        ----------------------------------------------- */
        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .tabela th {
            background: #F4FAF9;
            color: #6D7F88;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 2px solid #D9E7E5;
        }

        .tabela td {
            padding: 10px 12px;
            border-bottom: 1px solid #EEF5F4;
            color: #1F3440;
            vertical-align: middle;
        }

        .tabela tr:last-child td {
            border-bottom: none;
        }

        .tabela tr:hover td {
            background: #F4FAF9;
        }

        /* -----------------------------------------------
           COR DO LOCAL
        ----------------------------------------------- */
        .cor-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }

        /* -----------------------------------------------
           BADGE SITUAÇÃO
        ----------------------------------------------- */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-realizado              { background: #C9F2E2; color: #2E9C7C; }
        .badge-realizado_parcialmente { background: #D6ECFF; color: #3A7BD5; }
        .badge-nao_realizado          { background: #FEE2E2; color: #C24141; }
        .badge-substituido            { background: #FEF3C7; color: #D97706; }

        /* -----------------------------------------------
           RESUMO POR LOCAL
        ----------------------------------------------- */
        .local-resumo {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .local-linha {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: #F4FAF9;
            border-radius: 8px;
            border: 1px solid #D9E7E5;
        }

        .local-nome {
            flex: 1;
            font-weight: 600;
            font-size: 13px;
        }

        .local-stat {
            text-align: center;
            min-width: 60px;
        }

        .local-stat-valor {
            font-size: 16px;
            font-weight: 700;
            color: #1F3440;
        }

        .local-stat-label {
            font-size: 10px;
            color: #6D7F88;
            text-transform: uppercase;
        }

        /* -----------------------------------------------
           RODAPÉ
        ----------------------------------------------- */
        .rodape {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #D9E7E5;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #A0B4BC;
        }

        /* -----------------------------------------------
           BOTÃO IMPRIMIR (some no PDF)
        ----------------------------------------------- */
        .btn-imprimir {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #2A9D8F;
            color: #fff;
            border: none;
            padding: 14px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(42, 157, 143, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            z-index: 999;
        }

        .btn-imprimir:hover {
            background: #227F75;
            transform: translateY(-2px);
        }

        .btn-voltar {
            position: fixed;
            bottom: 24px;
            left: 24px;
            background: #fff;
            color: #1F3440;
            border: 1.5px solid #D9E7E5;
            padding: 14px 24px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-voltar:hover {
            border-color: #2A9D8F;
            color: #2A9D8F;
        }

        /* -----------------------------------------------
           CSS DE IMPRESSÃO
        ----------------------------------------------- */
        @media print {
            body {
                padding: 16px;
                font-size: 11px;
            }

            .btn-imprimir,
            .btn-voltar {
                display: none !important;
            }

            .secao {
                page-break-inside: avoid;
            }

            .tabela tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- CABEÇALHO -->
    <div class="header">
        <div class="header-logo">
            <span>🩺</span>
            <h1>MedBoard</h1>
        </div>
        <div class="header-info">
            <h2>Relatório de Atendimentos</h2>
            <p><?= $meses_pt[$mes] ?> de <?= $ano ?></p>
            <p><?= htmlspecialchars($_SESSION['nome']) ?></p>
            <p>Gerado em <?= date('d/m/Y \à\s H:i') ?></p>
        </div>
    </div>

    <!-- KPIs -->
    <div class="secao">
        <div class="secao-titulo">Resumo do mês</div>
        <div class="kpis-grid">

            <div class="kpi-card destaque">
                <span class="kpi-label">Total atendidos</span>
                <span class="kpi-valor"><?= $total_atendidos ?></span>
                <span class="kpi-sub" style="color:rgba(255,255,255,0.7)">
                    <?= $resumo['total_turnos'] ?? 0 ?> turno(s)
                </span>
            </div>

            <div class="kpi-card">
                <span class="kpi-label">Média por turno</span>
                <span class="kpi-valor"><?= round($resumo['media_por_turno'] ?? 0, 1) ?></span>
                <span class="kpi-sub">pacientes/turno</span>
            </div>

            <div class="kpi-card">
                <span class="kpi-label">Taxa de falta</span>
                <span class="kpi-valor"><?= $taxa_falta ?>%</span>
                <span class="kpi-sub"><?= $total_faltas ?> falta(s)</span>
            </div>

            <div class="kpi-card">
                <span class="kpi-label">Cancelamentos</span>
                <span class="kpi-valor"><?= $resumo['total_cancelamentos'] ?? 0 ?></span>
                <span class="kpi-sub">no mês</span>
            </div>

            <div class="kpi-card">
                <span class="kpi-label">Encaixes</span>
                <span class="kpi-valor"><?= $resumo['total_encaixes'] ?? 0 ?></span>
                <span class="kpi-sub">no mês</span>
            </div>

            <div class="kpi-card">
                <span class="kpi-label">Tempo médio</span>
                <span class="kpi-valor">
                    <?= $resumo['media_tempo']
                        ? round($resumo['media_tempo']) . 'min'
                        : '—' ?>
                </span>
                <span class="kpi-sub">por consulta</span>
            </div>

        </div>
    </div>

    <!-- POR LOCAL -->
    <?php if ($por_local): ?>
    <div class="secao">
        <div class="secao-titulo">Atendimentos por local</div>
        <div class="local-resumo">
            <?php foreach ($por_local as $loc): ?>
                <div class="local-linha">
                    <span class="cor-dot"
                          style="background:<?= htmlspecialchars($loc['cor_identificacao']) ?>">
                    </span>
                    <span class="local-nome">
                        <?= htmlspecialchars($loc['nome']) ?>
                    </span>
                    <div class="local-stat">
                        <div class="local-stat-valor"><?= $loc['total_atendidos'] ?></div>
                        <div class="local-stat-label">atendidos</div>
                    </div>
                    <div class="local-stat">
                        <div class="local-stat-valor"><?= $loc['total_turnos'] ?></div>
                        <div class="local-stat-label">turnos</div>
                    </div>
                    <div class="local-stat">
                        <div class="local-stat-valor"><?= round($loc['media_turno'], 1) ?></div>
                        <div class="local-stat-label">média</div>
                    </div>
                    <div class="local-stat">
                        <div class="local-stat-valor"><?= $loc['total_faltas'] ?></div>
                        <div class="local-stat-label">faltas</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABELA DE ATENDIMENTOS -->
    <div class="secao">
        <div class="secao-titulo">
            Detalhamento — <?= count($atendimentos) ?> registro(s)
        </div>

        <?php if ($atendimentos): ?>
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Local</th>
                        <th>Turno</th>
                        <th style="text-align:center">Atendidos</th>
                        <th style="text-align:center">Faltas</th>
                        <th style="text-align:center">Encaixes</th>
                        <th style="text-align:center">Tempo médio</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($atendimentos as $at): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= date('d/m', strtotime($at['data_trabalho'])) ?>
                                </strong>
                                <br>
                                <span style="color:#6D7F88; font-size:10px;">
                                    <?= $dias_semana_pt[date('w', strtotime($at['data_trabalho']))] ?>
                                </span>
                            </td>
                            <td>
                                <span class="cor-dot"
                                      style="background:<?= htmlspecialchars($at['cor_identificacao']) ?>">
                                </span>
                                <?= htmlspecialchars($at['local_nome']) ?>
                            </td>
                            <td>
                                <?= ucfirst(str_replace('/', ' e ', $at['turno'])) ?>
                                <br>
                                <span style="color:#6D7F88; font-size:10px;">
                                    <?= date('H:i', strtotime($at['hora_inicio'])) ?>
                                    –
                                    <?= date('H:i', strtotime($at['hora_fim'])) ?>
                                </span>
                            </td>
                            <td style="text-align:center; font-weight:700; font-size:16px; color:#2A9D8F;">
                                <?= $at['pacientes_atendidos'] ?>
                            </td>
                            <td style="text-align:center; font-weight:600; color:#C24141;">
                                <?= $at['faltas'] ?>
                            </td>
                            <td style="text-align:center; font-weight:600; color:#3A7BD5;">
                                <?= $at['encaixes'] ?>
                            </td>
                            <td style="text-align:center; color:#6D7F88;">
                                <?= $at['tempo_medio_consulta']
                                    ? $at['tempo_medio_consulta'] . 'min'
                                    : '—' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $at['situacao_dia'] ?>">
                                    <?= $situacoes[$at['situacao_dia']] ?? $at['situacao_dia'] ?>
                                </span>
                                <?php if ($at['observacao']): ?>
                                    <br>
                                    <span style="font-size:10px; color:#6D7F88;">
                                        <?= htmlspecialchars($at['observacao']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#6D7F88; text-align:center; padding:24px 0;">
                Nenhum atendimento registrado neste mês.
            </p>
        <?php endif; ?>
    </div>

    <!-- RODAPÉ -->
    <div class="rodape">
        <span>MedBoard — Sistema de Gestão Médica</span>
        <span><?= htmlspecialchars($_SESSION['nome']) ?></span>
        <span>Gerado em <?= date('d/m/Y \à\s H:i') ?></span>
    </div>

    <!-- BOTÕES -->
    <button class="btn-imprimir" onclick="window.print()">
        🖨️ Exportar PDF
    </button>

    <a href="relatorios.php?mes=<?= $mes ?>&ano=<?= $ano ?>"
       class="btn-voltar">
        ← Voltar
    </a>

</body>
</html>