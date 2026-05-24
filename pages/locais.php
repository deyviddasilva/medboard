<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

verificar_sessao();

$pdo        = conectar();
$id_usuario = $_SESSION['id_usuario'];
$erro       = '';
$sucesso    = '';

// -----------------------------------------------
// AÇÃO: CADASTRAR NOVO LOCAL
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {

        $nome    = trim($_POST['nome']    ?? '');
        $cidade  = trim($_POST['cidade']  ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $bairro  = trim($_POST['bairro']  ?? '');
        $obs     = trim($_POST['observacao'] ?? '');
        $cor     = $_POST['cor_identificacao'] ?? '#3B82F6';

        if (empty($nome) || empty($cidade)) {
            $erro = 'Nome e cidade são obrigatórios.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO locais_trabalho 
                    (id_usuario, nome, endereco, bairro, cidade, observacao, cor_identificacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_usuario, $nome, $endereco ?: null,
                $bairro ?: null, $cidade, $obs ?: null, $cor
            ]);
            $sucesso = 'Local cadastrado com sucesso!';
        }
    }
}

// -----------------------------------------------
// AÇÃO: EXCLUIR LOCAL
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {

        $id_local = (int)($_POST['id_local'] ?? 0);

        if ($id_local > 0) {
            // verifica se pertence ao usuário
            $stmt = $pdo->prepare("
                SELECT id_local FROM locais_trabalho 
                WHERE id_local = ? AND id_usuario = ?
            ");
            $stmt->execute([$id_local, $id_usuario]);

            if ($stmt->fetch()) {
                $pdo->prepare("DELETE FROM locais_trabalho WHERE id_local = ?")
                    ->execute([$id_local]);
                $sucesso = 'Local removido com sucesso!';
            }
        }
    }
}

// -----------------------------------------------
// AÇÃO: ATIVAR / DESATIVAR LOCAL
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
        $id_local = (int)($_POST['id_local'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT ativo FROM locais_trabalho 
            WHERE id_local = ? AND id_usuario = ?
        ");
        $stmt->execute([$id_local, $id_usuario]);
        $local = $stmt->fetch();

        if ($local) {
            $novo_status = $local['ativo'] ? 0 : 1;
            $pdo->prepare("UPDATE locais_trabalho SET ativo = ? WHERE id_local = ?")
                ->execute([$novo_status, $id_local]);
            $sucesso = $novo_status ? 'Local ativado!' : 'Local desativado!';
        }
    }
}

// -----------------------------------------------
// AÇÃO: ATUALIZAR LOCAL
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida.';
    } else {
        $id_local = (int)($_POST['id_local'] ?? 0);
        $nome     = trim($_POST['nome']      ?? '');
        $cidade   = trim($_POST['cidade']    ?? '');
        $endereco = trim($_POST['endereco']  ?? '');
        $bairro   = trim($_POST['bairro']    ?? '');
        $obs      = trim($_POST['observacao'] ?? '');
        $cor      = $_POST['cor_identificacao'] ?? '#3B82F6';

        if (empty($nome) || empty($cidade)) {
            $erro = 'Nome e cidade são obrigatórios.';
        } else {
            $stmt = $pdo->prepare("
                SELECT id_local FROM locais_trabalho
                WHERE id_local = ? AND id_usuario = ?
            ");
            $stmt->execute([$id_local, $id_usuario]);

            if ($stmt->fetch()) {
                $pdo->prepare("
                    UPDATE locais_trabalho SET
                        nome              = ?,
                        cidade            = ?,
                        endereco          = ?,
                        bairro            = ?,
                        observacao        = ?,
                        cor_identificacao = ?
                    WHERE id_local = ?
                ")->execute([
                    $nome, $cidade,
                    $endereco ?: null,
                    $bairro   ?: null,
                    $obs      ?: null,
                    $cor, $id_local
                ]);
                $sucesso = 'Local atualizado com sucesso!';
            }
        }
    }
}

// pré-carrega local para edição
$local_editar = null;
if (!empty($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM locais_trabalho
        WHERE id_local = ? AND id_usuario = ?
    ");
    $stmt->execute([(int)$_GET['editar'], $id_usuario]);
    $local_editar = $stmt->fetch();
}

// -----------------------------------------------
// BUSCA: TODOS OS LOCAIS DO USUÁRIO
// -----------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM locais_trabalho 
    WHERE id_usuario = ? 
    ORDER BY ativo DESC, nome ASC
");
$stmt->execute([$id_usuario]);
$locais = $stmt->fetchAll();

$titulo_pagina = 'Locais de Trabalho';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locais — MedBoard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="layout">

<?php include '../includes/sidebar.php'; ?>

<div class="conteudo">

    <?php include '../includes/header.php'; ?>

    <main class="main">

        <!-- ALERTAS -->
        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($local_editar): ?>
        <div class="card" style="border: 2px solid #3b82f6;">
            <div class="card-header">
                <h3>✏️ Editando: <?= htmlspecialchars($local_editar['nome']) ?></h3>
                <a href="locais.php" class="btn-secondary">✕ Cancelar</a>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="form-grid">
                    <input type="hidden" name="acao" value="atualizar">
                    <input type="hidden" name="id_local"
                           value="<?= $local_editar['id_local'] ?>">
                    <input type="hidden" name="csrf_token"
                           value="<?= gerar_csrf_token() ?>">

                    <div class="campo">
                        <label>Nome do local *</label>
                        <input type="text" name="nome" required
                               value="<?= htmlspecialchars($local_editar['nome']) ?>">
                    </div>

                    <div class="campo">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" required
                               value="<?= htmlspecialchars($local_editar['cidade']) ?>">
                    </div>

                    <div class="campo">
                        <label>Bairro</label>
                        <input type="text" name="bairro"
                               value="<?= htmlspecialchars($local_editar['bairro'] ?? '') ?>">
                    </div>

                    <div class="campo">
                        <label>Endereço</label>
                        <input type="text" name="endereco"
                               value="<?= htmlspecialchars($local_editar['endereco'] ?? '') ?>">
                    </div>

                    <div class="campo campo-cor">
                        <label>Cor de identificação</label>
                        <div class="cor-grupo">
                            <input type="color" name="cor_identificacao"
                                   value="<?= $local_editar['cor_identificacao'] ?>">
                        </div>
                    </div>

                    <div class="campo campo-full">
                        <label>Observação</label>
                        <input type="text" name="observacao"
                               value="<?= htmlspecialchars($local_editar['observacao'] ?? '') ?>">
                    </div>

                    <div class="campo campo-full">
                        <button type="submit" class="btn-primary">
                            💾 Salvar alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE CADASTRO -->
        <div class="card">
            <div class="card-header">
                <h3>📍 Novo local de trabalho</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="form-grid">
                    <input type="hidden" name="csrf_token" 
                                value="<?= gerar_csrf_token() ?>">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="campo">
                        <label>Nome do local *</label>
                        <input type="text" name="nome" 
                               placeholder="Ex: UBS Paulista, Hospital Central"
                               required>
                    </div>

                    <div class="campo">
                        <label>Cidade *</label>
                        <input type="text" name="cidade" 
                               placeholder="Ex: Recife"
                               required>
                    </div>

                    <div class="campo">
                        <label>Bairro</label>
                        <input type="text" name="bairro" 
                               placeholder="Ex: Boa Viagem">
                    </div>

                    <div class="campo">
                        <label>Endereço</label>
                        <input type="text" name="endereco" 
                               placeholder="Ex: Rua das Flores, 123">
                    </div>

                    <div class="campo campo-cor">
                        <label>Cor de identificação</label>
                        <div class="cor-grupo">
                            <input type="color" name="cor_identificacao" 
                                   value="#3B82F6" id="cor_input">
                            <span class="cor-dica">Cada local terá uma cor no calendário</span>
                        </div>
                    </div>

                    <div class="campo campo-full">
                        <label>Observação</label>
                        <input type="text" name="observacao" 
                               placeholder="Ex: Levar jaleco, Sala 3, Estacionamento grátis">
                    </div>

                    <div class="campo campo-full">
                        <button type="submit" class="btn-primary">
                            + Cadastrar local
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- LISTA DE LOCAIS -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Locais cadastrados</h3>
                <span class="badge badge-agendado"><?= count($locais) ?> local(is)</span>
            </div>
            <div class="card-body">
                <?php if ($locais): ?>
                    <div class="tabela-locais">
                        <?php foreach ($locais as $l): ?>
                            <div class="local-item <?= $l['ativo'] ? '' : 'inativo' ?>">

                                <div class="local-cor" 
                                     style="background: <?= htmlspecialchars($l['cor_identificacao']) ?>">
                                </div>

                                <div class="local-info">
                                    <strong><?= htmlspecialchars($l['nome']) ?></strong>
                                    <span>
                                        <?= htmlspecialchars($l['cidade']) ?>
                                        <?= $l['bairro'] ? '— ' . htmlspecialchars($l['bairro']) : '' ?>
                                    </span>
                                    <?php if ($l['endereco']): ?>
                                        <small><?= htmlspecialchars($l['endereco']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($l['observacao']): ?>
                                        <small class="obs">📌 <?= htmlspecialchars($l['observacao']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="local-acoes">
                                    <!-- EDITAR -->
                                    <a href="?editar=<?= $l['id_local'] ?>"
                                       class="btn-mini btn-info">
                                        ✏️ Editar
                                    </a>

                                    <!-- ATIVAR / DESATIVAR -->
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="acao" value="toggle">
                                        <input type="hidden" name="id_local" value="<?= $l['id_local'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                                        <button type="submit"
                                                class="btn-mini <?= $l['ativo'] ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $l['ativo'] ? '⏸ Desativar' : '▶ Ativar' ?>
                                        </button>
                                    </form>

                                    <!-- EXCLUIR -->
                                    <form method="POST" style="display:inline"
                                          onsubmit="return confirm('Tem certeza? Isso removerá o local.')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id_local" value="<?= $l['id_local'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= gerar_csrf_token() ?>">
                                        <button type="submit" class="btn-mini btn-danger">
                                            🗑 Excluir
                                        </button>
                                    </form>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="vazio">
                        Nenhum local cadastrado ainda.<br>
                        <small>Cadastre o primeiro local acima para começar.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <?php include '../includes/footer.php'; ?>

</div>
</body>
</html>