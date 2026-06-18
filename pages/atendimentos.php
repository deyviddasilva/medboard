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
$filtro_local  = (int)($_GET['local']  ?? 0);
$filtro_mes    = (int)($_GET['mes']    ?? date('n'));
$filtro_ano    = (int)($_GET['ano']    ?? date('Y'));
$filtro_turno  = $_GET['turno']        ?? '';

if ($filtro_mes < 1)  { $filtro_mes = 12; $filtro_ano--; }
if ($filtro_mes > 12) { $filtro_mes = 1;  $filtro_ano++; }

$meses_pt = [
    1  => 'Janeiro',   2  => 'Fevereiro', 3  => 'Março',
    4  => 'Abril',     5  => 'Maio',      6  => 'Junho',
    7  => 'Julho',     8  => 'Agosto',    9  => 'Setembro',
    10 => 'Outubro',   11 => 'Novembro',  12 => 'Dezembro'
];

$inicio_mes = sprintf('%04d-%02d-01', $filtro_ano, $filtro_mes);
$fim_mes    = date('Y-m-t', strtotime($inicio_mes));

// -----------------------------------------------
// BUSCA: LOCAIS (para o filtro)
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT id_local, nome, cor_identificacao
    FROM locais_trabalho
    WHERE id_usuario = ?
    ORDER BY nome ASC
");
$stmt->execute([$id_usuario]);
$locais = $stmt->fetchAll();

// -----------------------------------------------
// MONTA QUERY COM FILTROS DINÂMICOS
// -----------------------------------------------
$where  = ["a.id_usuario = ?"];
$params = [$id_usuario];

$where[]  = "a.data_trabalho BETWEEN ? AND ?";
$params[] = $inicio_mes;
$params[] = $fim_mes;

if ($filtro_local > 0) {
    $where[]  = "a.id_local = ?";
    $params[] = $filtro_local;
}

if (!empty($filtro_turno)) {
    $where[]  = "a.turno = ?";
    $params[] = $filtro_turno;
}

$where_sql = implode(' AND ', $where);

// -----------------------------------------------
// PAGINAÇÃO
// -----------------------------------------------
$por_pagina   = 10;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset       = ($pagina_atual - 1) * $por_pagina;

// total de registros
$stmt_total = $pdo->prepare("
    SELECT COUNT(r.id_registro) AS total
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho  l ON l.id_local  = a.id_local
    WHERE {$where_sql}
");
$stmt_total->execute($params);
$total_registros = (int)$stmt_total->fetch()['total'];
$total_paginas   = (int)ceil($total_registros / $por_pagina);

// -----------------------------------------------
// QUERY: ATENDIMENTOS COM FILTROS
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        r.id_registro,
        r.pacientes_atendidos,
        r.faltas,
        r.cancelamentos,
        r.encaixes,
        r.tempo_medio_consulta,
        r.hora_real_inicio,
        r.hora_real_fim,
        r.duracao_real_minutos,
        r.situacao_dia,
        r.observacao,
        a.id_agenda,
        a.data_trabalho,
        a.turno,
        a.hora_inicio,
        a.hora_fim,
        a.data_fim,
        a.status_agenda,
        l.nome            AS local_nome,
        l.cor_identificacao
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho  l ON l.id_local  = a.id_local
    WHERE {$where_sql}
    ORDER BY a.data_trabalho DESC, a.hora_inicio DESC
    LIMIT {$por_pagina} OFFSET {$offset}
");
$stmt->execute($params);
$atendimentos = $stmt->fetchAll();

// -----------------------------------------------
// QUERY: TOTAIS DO FILTRO ATUAL
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        COUNT(r.id_registro)         AS total_turnos,
        SUM(r.pacientes_atendidos)   AS total_atendidos,
        SUM(r.faltas)                AS total_faltas,
        SUM(r.cancelamentos)         AS total_cancelamentos,
        SUM(r.encaixes)              AS total_encaixes,
        AVG(r.pacientes_atendidos)   AS media_por_turno,
        AVG(r.tempo_medio_consulta)  AS media_tempo
    FROM registro_diario r
    JOIN agenda_trabalho a ON a.id_agenda = r.id_agenda
    JOIN locais_trabalho  l ON l.id_local  = a.id_local
    WHERE {$where_sql}
");
$stmt->execute($params);
$totais = $stmt->fetch();

$situacoes = [
    'realizado'              => 'Realizado',
    'realizado_parcialmente' => 'Realizado parcialmente',
    'nao_realizado'          => 'Não realizado',
    'substituido'            => 'Substituído',
];

$turnos_opcoes = [
    'manha'             => 'Manhã',
    'tarde'             => 'Tarde',
    'noite'             => 'Noite',
    'manha/tarde'       => 'Manhã e Tarde',
    'tarde/noite'       => 'Tarde e Noite',
    'manha/tarde/noite' => 'Dia inteiro',
];

$dias_semana_pt = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$titulo_pagina = 'Atendimentos';
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
    <title>Atendimentos — MedBoard</title>
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

        <!-- FILTROS -->
        <div class="card">
            <div class="card-header">
                <h3>🔍 Filtros</h3>
                <div class="mes-nav">
                    <a href="?mes=<?= $filtro_mes-1 ?>&ano=<?= $filtro_ano ?>&local=<?= $filtro_local ?>&turno=<?= $filtro_turno ?>"
                       class="btn-mes">‹</a>
                    <strong><?= $meses_pt[$filtro_mes] ?> <?= $filtro_ano ?></strong>
                    <a href="?mes=<?= $filtro_mes+1 ?>&ano=<?= $filtro_ano ?>&local=<?= $filtro_local ?>&turno=<?= $filtro_turno ?>"
                       class="btn-mes">›</a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="form-filtros">

                    <input type="hidden" name="mes" value="<?= $filtro_mes ?>">
                    <input type="hidden" name="ano" value="<?= $filtro_ano ?>">

                    <div class="campo">
                        <label>Local</label>
                        <select name="local">
                            <option value="0">Todos os locais</option>
                            <?php foreach ($locais as $l): ?>
                                <option value="<?= $l['id_local'] ?>"
                                    <?= $filtro_local === $l['id_local'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label>Turno</label>
                        <select name="turno">
                            <option value="">Todos os turnos</option>
                            <?php foreach ($turnos_opcoes as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= $filtro_turno === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo" style="align-self:flex-end;">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="atendimentos.php" class="btn-secondary"
                           style="margin-left:8px;">Limpar</a>
                    </div>

                </form>
            </div>
        </div>

        <!-- KPIs DO FILTRO -->
        <section class="kpis">

            <div class="card-kpi">
                <div class="kpi-icone">📋</div>
                <div class="kpi-info">
                    <span class="kpi-label">Turnos registrados</span>
                    <span class="kpi-valor"><?= $totais['total_turnos'] ?? 0 ?></span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">👥</div>
                <div class="kpi-info">
                    <span class="kpi-label">Total atendidos</span>
                    <span class="kpi-valor"><?= $totais['total_atendidos'] ?? 0 ?></span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">📈</div>
                <div class="kpi-info">
                    <span class="kpi-label">Média por turno</span>
                    <span class="kpi-valor">
                        <?= round($totais['media_por_turno'] ?? 0, 1) ?>
                    </span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">❌</div>
                <div class="kpi-info">
                    <span class="kpi-label">Faltas</span>
                    <span class="kpi-valor"><?= $totais['total_faltas'] ?? 0 ?></span>
                    <span class="kpi-sub">
                        <?= $totais['total_cancelamentos'] ?? 0 ?> cancelamento(s)
                    </span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">➕</div>
                <div class="kpi-info">
                    <span class="kpi-label">Encaixes</span>
                    <span class="kpi-valor"><?= $totais['total_encaixes'] ?? 0 ?></span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="kpi-icone">⏱</div>
                <div class="kpi-info">
                    <span class="kpi-label">Tempo médio</span>
                    <span class="kpi-valor">
                        <?= $totais['media_tempo']
                            ? round($totais['media_tempo']) . ' min'
                            : '—' ?>
                    </span>
                </div>
            </div>

        </section>

        <!-- LISTA DE ATENDIMENTOS -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Atendimentos — <?= $meses_pt[$filtro_mes] ?> <?= $filtro_ano ?></h3>
                <span class="badge badge-agendado">
                    <?= $total_registros ?> registro(s)
                </span>
            </div>
            <div class="card-body">

                <?php if ($atendimentos): ?>
                    <div class="atendimentos-lista">
                        <?php foreach ($atendimentos as $at): ?>
                            <div class="atendimento-item"
                                 style="border-left: 4px solid <?= $at['cor_identificacao'] ?>">

                                <!-- TOPO: DATA + LOCAL + BADGE -->
                                <div class="at-topo">
                                    <div class="at-data">
                                        <strong>
                                            <?= date('d/m/Y', strtotime($at['data_trabalho'])) ?>
                                        </strong>
                                        <span class="at-diaSemana">
                                            <?= $dias_semana_pt[date('w', strtotime($at['data_trabalho']))] ?>
                                        </span>
                                    </div>

                                    <div class="at-local">
                                        <span class="legenda-cor"
                                              style="background:<?= $at['cor_identificacao'] ?>">
                                        </span>
                                        <?= htmlspecialchars($at['local_nome']) ?>
                                    </div>

                                    <div class="at-turno">
                                        <?= ucfirst(str_replace('/', ' e ', $at['turno'])) ?>
                                        <small>
                                            <?= date('H:i', strtotime($at['hora_inicio'])) ?>
                                            –
                                            <?= date('H:i', strtotime($at['hora_fim'])) ?>
                                            <?php if (!empty($at['data_fim'])): ?>
                                                <span class="badge-virada">+1 dia</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <span class="badge badge-<?= $at['situacao_dia'] === 'realizado' ? 'concluido' : ($at['situacao_dia'] === 'nao_realizado' ? 'cancelado' : 'agendado') ?>">
                                        <?= $situacoes[$at['situacao_dia']] ?? $at['situacao_dia'] ?>
                                    </span>
                                </div>

                                <!-- NÚMEROS -->
                                <div class="at-numeros">

                                    <div class="at-num-item destaque">
                                        <span class="at-num-valor">
                                            <?= $at['pacientes_atendidos'] ?>
                                        </span>
                                        <span class="at-num-label">atendidos</span>
                                    </div>

                                    <div class="at-num-item">
                                        <span class="at-num-valor"><?= $at['faltas'] ?></span>
                                        <span class="at-num-label">faltas</span>
                                    </div>

                                    <div class="at-num-item">
                                        <span class="at-num-valor"><?= $at['cancelamentos'] ?></span>
                                        <span class="at-num-label">cancelamentos</span>
                                    </div>

                                    <div class="at-num-item">
                                        <span class="at-num-valor"><?= $at['encaixes'] ?></span>
                                        <span class="at-num-label">encaixes</span>
                                    </div>

                                    <?php if ($at['tempo_medio_consulta']): ?>
                                        <div class="at-num-item">
                                            <span class="at-num-valor">
                                                <?= $at['tempo_medio_consulta'] ?>min
                                            </span>
                                            <span class="at-num-label">tempo médio</span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($at['duracao_real_minutos']): ?>
                                        <div class="at-num-item">
                                            <span class="at-num-valor">
                                                <?= floor($at['duracao_real_minutos'] / 60) ?>h
                                                <?= $at['duracao_real_minutos'] % 60 ?>min
                                            </span>
                                            <span class="at-num-label">duração real</span>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <!-- HORÁRIO REAL SE DIFERENTE -->
                                <?php if ($at['hora_real_inicio'] && $at['hora_real_fim']): ?>
                                    <div class="at-horario-real">
                                        🕐 Horário real:
                                        <?= date('H:i', strtotime($at['hora_real_inicio'])) ?>
                                        –
                                        <?= date('H:i', strtotime($at['hora_real_fim'])) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- OBSERVAÇÃO -->
                                <?php if ($at['observacao']): ?>
                                    <div class="registro-obs">
                                        📌 <?= htmlspecialchars($at['observacao']) ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="vazio">
                        Nenhum atendimento registrado neste período.<br>
                        <small>
                            Tente outro mês ou
                            <a href="registro_dia.php">lance um registro →</a>
                        </small>
                    </div>
                <?php endif; ?>

                <?php if ($total_paginas > 1): ?>
                    <div class="paginacao">
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>&local=<?= $filtro_local ?>&turno=<?= $filtro_turno ?>&pagina=<?= $pagina_atual - 1 ?>"
                               class="btn-pagina">‹ Anterior</a>
                        <?php endif; ?>

                        <span class="pagina-info">
                            Página <?= $pagina_atual ?> de <?= $total_paginas ?>
                            (<?= $total_registros ?> registros)
                        </span>

                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?mes=<?= $filtro_mes ?>&ano=<?= $filtro_ano ?>&local=<?= $filtro_local ?>&turno=<?= $filtro_turno ?>&pagina=<?= $pagina_atual + 1 ?>"
                               class="btn-pagina">Próxima ›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>
</body>
</html>