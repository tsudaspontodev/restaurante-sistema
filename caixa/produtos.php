<?php

declare(strict_types=1);

session_start();

// Ajuste o caminho conforme a estrutura do seu projeto
// require_once __DIR__ . '/../config/db.php';
require_once '../config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com o banco de dados ($pdo) não encontrada.');
}

// Helper para respostas JSON em requisições AJAX
function responderJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function limparTexto(?string $valor): string
{
    return trim((string) $valor);
}

function converterPreco(string $valor): float
{
    $limpo = preg_replace('/[^\d,.-]/', '', $valor);
    if (str_contains($limpo, ',') && str_contains($limpo, '.')) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    } elseif (str_contains($limpo, ',')) {
        $limpo = str_replace(',', '.', $limpo);
    }
    return round((float) $limpo, 2);
}

// Processamento de ações via POST (AJAX ou formulário tradicional)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    $acao = $_POST['acao'] ?? '';

    try {
        switch ($acao) {
            // --- CATEGORIAS ---
            case 'salvar_categoria':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
                $nome = limparTexto($_POST['nome'] ?? '');
                $ordem = max(0, (int) ($_POST['ordem'] ?? 0));

                if ($nome === '') {
                    throw new InvalidArgumentException('O nome da categoria é obrigatório.');
                }

                if ($id) {
                    $stmt = $pdo->prepare(
                        'UPDATE categorias SET nome = :nome, ordem = :ordem WHERE id = :id'
                    );
                    $stmt->execute([
                        ':nome' => $nome,
                        ':ordem' => $ordem,
                        ':id' => $id,
                    ]);
                    $mensagem = 'Categoria atualizada com sucesso.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO categorias (nome, ordem) VALUES (:nome, :ordem)'
                    );
                    $stmt->execute([
                        ':nome' => $nome,
                        ':ordem' => $ordem,
                    ]);
                    $mensagem = 'Categoria cadastrada com sucesso.';
                }

                if ($isAjax) {
                    responderJson(['sucesso' => true, 'mensagem' => $mensagem]);
                }
                $_SESSION['flash_sucesso'] = $mensagem;
                header('Location: produtos.php?aba=categorias');
                exit;

            case 'excluir_categoria':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException('ID de categoria inválido.');
                }

                $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE id_categoria = :id');
                $stmtCheck->execute([':id' => $id]);
                if ((int) $stmtCheck->fetchColumn() > 0) {
                    throw new RuntimeException('Não é possível excluir uma categoria que possui produtos vinculados.');
                }

                $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $mensagem = 'Categoria excluída com sucesso.';

                if ($isAjax) {
                    responderJson(['sucesso' => true, 'mensagem' => $mensagem]);
                }
                $_SESSION['flash_sucesso'] = $mensagem;
                header('Location: produtos.php?aba=categorias');
                exit;

                // --- PRODUTOS ---
            case 'salvar_produto':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
                $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
                $nome = limparTexto($_POST['nome'] ?? '');
                $descricao = limparTexto($_POST['descricao'] ?? '');
                $preco = converterPreco((string) ($_POST['preco'] ?? '0'));
                $imagemUrl = limparTexto($_POST['imagem_url'] ?? '');
                $disponivel = isset($_POST['disponivel']) ? 1 : 0;

                if ($nome === '') {
                    throw new InvalidArgumentException('O nome do produto é obrigatório.');
                }
                if (!$categoriaId) {
                    throw new InvalidArgumentException('Selecione uma categoria válida.');
                }
                if ($preco < 0) {
                    throw new InvalidArgumentException('O preço não pode ser negativo.');
                }
                if ($imagemUrl !== '' && !filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Informe uma URL de imagem válida.');
                }
                if ($imagemUrl === '') {
                    $imagemUrl = 'https://placehold.co/400x300?text=Produto';
                }

                $stmtCat = $pdo->prepare('SELECT id FROM categorias WHERE id = :id');
                $stmtCat->execute([':id' => $categoriaId]);
                if (!$stmtCat->fetchColumn()) {
                    throw new InvalidArgumentException('A categoria selecionada não existe.');
                }

                if ($id) {
                    $stmt = $pdo->prepare(
                        'UPDATE produtos
                         SET id_categoria = :categoria_id,
                             nome = :nome,
                             descricao = :descricao,
                             preco = :preco,
                             imagem_url = :imagem_url,
                             ativo = :disponivel
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':categoria_id' => $categoriaId,
                        ':nome' => $nome,
                        ':descricao' => $descricao !== '' ? $descricao : null,
                        ':preco' => $preco,
                        ':imagem_url' => $imagemUrl,
                        ':disponivel' => $disponivel,
                        ':id' => $id,
                    ]);
                    $mensagem = 'Produto atualizado com sucesso.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO produtos (id_categoria, nome, descricao, preco, imagem_url, ativo)
                         VALUES (:categoria_id, :nome, :descricao, :preco, :imagem_url, :disponivel)'
                    );
                    $stmt->execute([
                        ':categoria_id' => $categoriaId,
                        ':nome' => $nome,
                        ':descricao' => $descricao !== '' ? $descricao : null,
                        ':preco' => $preco,
                        ':imagem_url' => $imagemUrl,
                        ':disponivel' => $disponivel,
                    ]);
                    $mensagem = 'Produto cadastrado com sucesso.';
                }

                if ($isAjax) {
                    responderJson(['sucesso' => true, 'mensagem' => $mensagem]);
                }
                $_SESSION['flash_sucesso'] = $mensagem;
                header('Location: produtos.php');
                exit;

            case 'alternar_disponibilidade':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException('ID de produto inválido.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE produtos
                     SET ativo = IF(ativo = 1, 0, 1)
                     WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);

                $stmtStatus = $pdo->prepare('SELECT ativo FROM produtos WHERE id = :id');
                $stmtStatus->execute([':id' => $id]);
                $novoStatus = (int) $stmtStatus->fetchColumn();

                if ($isAjax) {
                    responderJson([
                        'sucesso' => true,
                        'disponivel' => $novoStatus,
                        'mensagem' => $novoStatus === 1 ? 'Produto marcado como disponível.' : 'Produto marcado como indisponível.',
                    ]);
                }
                $_SESSION['flash_sucesso'] = 'Disponibilidade atualizada.';
                header('Location: produtos.php');
                exit;

            case 'excluir_produto':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException('ID de produto inválido.');
                }

                $stmtUso = $pdo->prepare('SELECT COUNT(*) FROM itens_pedido WHERE id_produto = :id');
                $stmtUso->execute([':id' => $id]);
                if ((int) $stmtUso->fetchColumn() > 0) {
                    throw new RuntimeException('Este produto já foi vendido e não pode ser excluído. Use "Alternar Status" para deixá-lo indisponível.');
                }

                $stmt = $pdo->prepare('DELETE FROM produtos WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $mensagem = 'Produto excluído com sucesso.';

                if ($isAjax) {
                    responderJson(['sucesso' => true, 'mensagem' => $mensagem]);
                }
                $_SESSION['flash_sucesso'] = $mensagem;
                header('Location: produtos.php');
                exit;

            default:
                throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (Throwable $e) {
        $erroMsg = $e instanceof PDOException
            ? 'Erro ao processar operação no banco de dados.'
            : $e->getMessage();

        if ($isAjax) {
            responderJson(['sucesso' => false, 'mensagem' => $erroMsg], 400);
        }
        $_SESSION['flash_erro'] = $erroMsg;
        header('Location: produtos.php');
        exit;
    }
}

// Filtros da listagem de produtos
$abaAtiva = ($_GET['aba'] ?? 'produtos') === 'categorias' ? 'categorias' : 'produtos';
$busca = limparTexto($_GET['busca'] ?? '');
$filtroCategoria = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT) ?: 0;
$filtroStatus = $_GET['status'] ?? 'todos'; // todos | disponivel | indisponivel

// Busca categorias com contagem de produtos
$stmtCategorias = $pdo->query(
    'SELECT c.id, c.nome, c.ordem, COUNT(p.id) AS total_produtos
     FROM categorias c
     LEFT JOIN produtos p ON p.id_categoria = c.id
     GROUP BY c.id, c.nome, c.ordem
     ORDER BY c.ordem ASC, c.nome ASC'
);
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
$mesasNav = $pdo->query('SELECT numero FROM mesas ORDER BY numero ASC')->fetchAll(PDO::FETCH_ASSOC);

// Busca produtos filtrados
$sqlProdutos = 'SELECT p.id, p.id_categoria AS categoria_id, p.nome, p.descricao, p.preco, p.imagem_url,
                       p.ativo AS disponivel, c.nome AS categoria_nome
                FROM produtos p
                INNER JOIN categorias c ON c.id = p.id_categoria
                WHERE 1=1';
$paramsProdutos = [];

if ($busca !== '') {
    $sqlProdutos .= ' AND (p.nome LIKE :busca OR p.descricao LIKE :busca)';
    $paramsProdutos[':busca'] = '%' . $busca . '%';
}
if ($filtroCategoria > 0) {
    $sqlProdutos .= ' AND p.id_categoria = :categoria_id';
    $paramsProdutos[':categoria_id'] = $filtroCategoria;
}
if ($filtroStatus === 'disponivel') {
    $sqlProdutos .= ' AND p.ativo = 1';
} elseif ($filtroStatus === 'indisponivel') {
    $sqlProdutos .= ' AND p.ativo = 0';
}

$sqlProdutos .= ' ORDER BY c.ordem ASC, c.nome ASC, p.nome ASC';
$stmtProdutos = $pdo->prepare($sqlProdutos);
$stmtProdutos->execute($paramsProdutos);
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

// Métricas rápidas do topo
$totalProdutos = count($produtos);
$produtosDisponiveis = count(array_filter($produtos, static fn(array $p): bool => (int) $p['disponivel'] === 1));
$totalCategorias = count($categorias);

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos e Categorias — Caixa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg: #f4f6f9;
            --surface: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #0f766e;
            --primary-hover: #115e59;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --danger-bg: #fee2e2;
            --danger-text: #991b1b;
            --radius: 10px;
            --shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        .navbar .btn {
            margin-bottom: 0;
            padding: .25rem .5rem;
            font-size: .875rem;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .topbar h1 {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .topbar p {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 15px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-danger:hover {
            background: var(--danger-hover);
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 0.82rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .stat-label {
            font-size: 0.82rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-value {
            font-size: 1.65rem;
            font-weight: 700;
            margin-top: 4px;
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 8px;
        }

        .tab-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-radius: 8px;
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            flex: 1;
        }

        .input,
        .select,
        .textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.92rem;
            background: #fff;
            color: var(--text);
        }

        .filters .input {
            max-width: 240px;
        }

        .filters .select {
            max-width: 200px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .badge-ok {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .badge-off {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 500;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 50;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal {
            background: var(--surface);
            border-radius: var(--radius);
            width: 100%;
            max-width: 520px;
            padding: 22px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 0.86rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <?php $paginaAtiva = 'produtos'; require __DIR__ . '/_navbar_gerencia.php'; ?>
    <div class="container">
        <div class="topbar">
            <div>
                <h1>Produtos e Categorias</h1>
                <p>Gerencie o catálogo, preços, imagens e disponibilidade do caixa</p>
            </div>
        </div>

        <div id="alertaDinamico"></div>

        <?php if ($flashSucesso): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($flashErro): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Produtos Listados</div>
                <div class="stat-value"><?= $totalProdutos ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Disponíveis para Venda</div>
                <div class="stat-value"><?= $produtosDisponiveis ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Categorias</div>
                <div class="stat-value"><?= $totalCategorias ?></div>
            </div>
        </div>

        <div class="tabs">
            <button type="button" class="tab-btn <?= $abaAtiva === 'produtos' ? 'active' : '' ?>" data-tab="produtos">
                Produtos
            </button>
            <button type="button" class="tab-btn <?= $abaAtiva === 'categorias' ? 'active' : '' ?>" data-tab="categorias">
                Categorias
            </button>
        </div>

        <!-- ABA PRODUTOS -->
        <section id="sec-produtos" class="card <?= $abaAtiva !== 'produtos' ? 'hidden' : '' ?>">
            <div class="toolbar">
                <form method="GET" class="filters" id="formFiltroProdutos">
                    <input type="hidden" name="aba" value="produtos">
                    <input
                        type="text"
                        name="busca"
                        class="input"
                        placeholder="Buscar produto..."
                        value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>">
                    <select name="categoria_id" class="select">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= $filtroCategoria === (int) $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="select">
                        <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos os status</option>
                        <option value="disponivel" <?= $filtroStatus === 'disponivel' ? 'selected' : '' ?>>Disponíveis</option>
                        <option value="indisponivel" <?= $filtroStatus === 'indisponivel' ? 'selected' : '' ?>>Indisponíveis</option>
                    </select>
                    <button type="submit" class="btn btn-outline">Filtrar</button>
                    <a href="produtos.php" class="btn btn-outline">Limpar</a>
                </form>

                <button type="button" class="btn btn-primary" id="btnNovoProduto">+ Novo Produto</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Imagem</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--muted); padding:28px;">
                                    Nenhum produto encontrado com os filtros atuais.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $prod): ?>
                                <tr data-produto-id="<?= (int) $prod['id'] ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($prod['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($prod['descricao'])): ?>
                                            <div style="font-size:0.82rem; color:var(--muted);">
                                                <?= htmlspecialchars($prod['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($prod['categoria_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (!empty($prod['imagem_url'])): ?>
                                            <img src="<?= htmlspecialchars($prod['imagem_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:64px; height:48px; object-fit:cover; border-radius:6px;">
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?= number_format((float) $prod['preco'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= (int) $prod['disponivel'] === 1 ? 'badge-ok' : 'badge-off' ?>" data-badge-status>
                                            <?= (int) $prod['disponivel'] === 1 ? 'Disponível' : 'Indisponível' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-sm btn-toggle-disp"
                                                data-id="<?= (int) $prod['id'] ?>">
                                                Alternar Status
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-sm btn-editar-produto"
                                                data-id="<?= (int) $prod['id'] ?>"
                                                data-categoria-id="<?= (int) $prod['categoria_id'] ?>"
                                                data-nome="<?= htmlspecialchars($prod['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-descricao="<?= htmlspecialchars((string) $prod['descricao'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-preco="<?= number_format((float) $prod['preco'], 2, '.', '') ?>"
                                                data-imagem-url="<?= htmlspecialchars((string) $prod['imagem_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-disponivel="<?= (int) $prod['disponivel'] ?>">
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm btn-excluir-produto"
                                                data-id="<?= (int) $prod['id'] ?>"
                                                data-nome="<?= htmlspecialchars($prod['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ABA CATEGORIAS -->
        <section id="sec-categorias" class="card <?= $abaAtiva !== 'categorias' ? 'hidden' : '' ?>">
            <div class="toolbar">
                <div>
                    <strong>Categorias Cadastradas</strong>
                    <div style="font-size:0.84rem; color:var(--muted);">Organize os produtos por grupo</div>
                </div>
                <button type="button" class="btn btn-primary" id="btnNovaCategoria">+ Nova Categoria</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Nome</th>
                            <th>Produtos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--muted); padding:28px;">
                                    Nenhuma categoria cadastrada até o momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><?= (int) $cat['ordem'] ?></td>
                                    <td><strong><?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= (int) $cat['total_produtos'] ?></td>
                                    <td>
                                        <div class="actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-sm btn-editar-categoria"
                                                data-id="<?= (int) $cat['id'] ?>"
                                                data-nome="<?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-ordem="<?= (int) $cat['ordem'] ?>">
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm btn-excluir-categoria"
                                                data-id="<?= (int) $cat['id'] ?>"
                                                data-nome="<?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                                Excluir
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- MODAL PRODUTO -->
    <div class="modal-backdrop" id="modalProduto">
        <div class="modal">
            <div class="modal-header">
                <h2 id="tituloModalProduto" style="font-size:1.2rem;">Novo Produto</h2>
                <button type="button" class="btn btn-outline btn-sm" data-fechar-modal="modalProduto">✕</button>
            </div>
            <form id="formProduto" method="POST">
                <input type="hidden" name="acao" value="salvar_produto">
                <input type="hidden" name="id" id="prodId">

                <div class="form-group">
                    <label for="prodNome">Nome do Produto *</label>
                    <input type="text" name="nome" id="prodNome" class="input" required>
                </div>

                <div class="form-group">
                    <label for="prodCategoria">Categoria *</label>
                    <select name="categoria_id" id="prodCategoria" class="select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>">
                                <?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prodPreco">Preço (R$) *</label>
                        <input type="text" name="preco" id="prodPreco" class="input" placeholder="0,00" required>
                    </div>
                    <div class="form-group">
                        <label for="prodImagem">URL da Imagem</label>
                        <input type="url" name="imagem_url" id="prodImagem" class="input" placeholder="https://...">
                    </div>
                </div>

                <div class="form-group">
                    <label for="prodDescricao">Descrição</label>
                    <textarea name="descricao" id="prodDescricao" class="textarea" rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="disponivel" id="prodDisponivel" value="1" checked>
                        Produto disponível para venda
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-fechar-modal="modalProduto">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Produto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CATEGORIA -->
    <div class="modal-backdrop" id="modalCategoria">
        <div class="modal">
            <div class="modal-header">
                <h2 id="tituloModalCategoria" style="font-size:1.2rem;">Nova Categoria</h2>
                <button type="button" class="btn btn-outline btn-sm" data-fechar-modal="modalCategoria">✕</button>
            </div>
            <form id="formCategoria" method="POST">
                <input type="hidden" name="acao" value="salvar_categoria">
                <input type="hidden" name="id" id="catId">

                <div class="form-group">
                    <label for="catNome">Nome da Categoria *</label>
                    <input type="text" name="nome" id="catNome" class="input" required>
                </div>

                <div class="form-group">
                    <label for="catOrdem">Ordem de exibição</label>
                    <input type="number" name="ordem" id="catOrdem" class="input" min="0" step="1" value="0">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-fechar-modal="modalCategoria">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Categoria</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const alertaBox = document.getElementById('alertaDinamico');

            function mostrarAlerta(mensagem, tipo = 'success') {
                const div = document.createElement('div');
                div.className = `alert alert-${tipo === 'success' ? 'success' : 'error'}`;
                div.textContent = mensagem;
                alertaBox.replaceChildren(div);
                alertaBox.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }

            async function enviarAcao(formData) {
                const resp = await fetch('produtos.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await resp.json();
                if (!resp.ok || !data.sucesso) {
                    throw new Error(data.mensagem || 'Erro na operação.');
                }
                return data;
            }

            // Troca de abas
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tab = btn.dataset.tab;
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    document.getElementById('sec-produtos').classList.toggle('hidden', tab !== 'produtos');
                    document.getElementById('sec-categorias').classList.toggle('hidden', tab !== 'categorias');
                });
            });

            // Modais
            const abrirModal = id => document.getElementById(id).classList.add('open');
            const fecharModal = id => document.getElementById(id).classList.remove('open');

            document.querySelectorAll('[data-fechar-modal]').forEach(btn => {
                btn.addEventListener('click', () => fecharModal(btn.dataset.fecharModal));
            });

            // Novo / Editar Produto
            const modalProduto = document.getElementById('modalProduto');
            const formProduto = document.getElementById('formProduto');

            document.getElementById('btnNovoProduto').addEventListener('click', () => {
                formProduto.reset();
                document.getElementById('prodId').value = '';
                document.getElementById('prodDisponivel').checked = true;
                document.getElementById('tituloModalProduto').textContent = 'Novo Produto';
                abrirModal('modalProduto');
            });

            document.querySelectorAll('.btn-editar-produto').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('prodId').value = btn.dataset.id;
                    document.getElementById('prodNome').value = btn.dataset.nome;
                    document.getElementById('prodCategoria').value = btn.dataset.categoriaId;
                    document.getElementById('prodPreco').value = String(btn.dataset.preco).replace('.', ',');
                    document.getElementById('prodImagem').value = btn.dataset.imagemUrl;
                    document.getElementById('prodDescricao').value = btn.dataset.descricao;
                    document.getElementById('prodDisponivel').checked = btn.dataset.disponivel === '1';
                    document.getElementById('tituloModalProduto').textContent = 'Editar Produto';
                    abrirModal('modalProduto');
                });
            });

            formProduto.addEventListener('submit', async e => {
                e.preventDefault();
                try {
                    const data = await enviarAcao(new FormData(formProduto));
                    fecharModal('modalProduto');
                    mostrarAlerta(data.mensagem, 'success');
                    setTimeout(() => window.location.reload(), 500);
                } catch (err) {
                    mostrarAlerta(err.message, 'error');
                }
            });

            // Alternar disponibilidade via AJAX
            document.querySelectorAll('.btn-toggle-disp').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const fd = new FormData();
                    fd.append('acao', 'alternar_disponibilidade');
                    fd.append('id', btn.dataset.id);
                    try {
                        const data = await enviarAcao(fd);
                        const row = btn.closest('tr');
                        const badge = row.querySelector('[data-badge-status]');
                        if (data.disponivel === 1) {
                            badge.textContent = 'Disponível';
                            badge.className = 'badge badge-ok';
                        } else {
                            badge.textContent = 'Indisponível';
                            badge.className = 'badge badge-off';
                        }
                        mostrarAlerta(data.mensagem, 'success');
                    } catch (err) {
                        mostrarAlerta(err.message, 'error');
                    }
                });
            });

            // Excluir produto
            document.querySelectorAll('.btn-excluir-produto').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm(`Deseja realmente excluir o produto "${btn.dataset.nome}"?`)) return;
                    const fd = new FormData();
                    fd.append('acao', 'excluir_produto');
                    fd.append('id', btn.dataset.id);
                    try {
                        const data = await enviarAcao(fd);
                        mostrarAlerta(data.mensagem, 'success');
                        btn.closest('tr').remove();
                    } catch (err) {
                        mostrarAlerta(err.message, 'error');
                    }
                });
            });

            // Nova / Editar Categoria
            const formCategoria = document.getElementById('formCategoria');

            document.getElementById('btnNovaCategoria').addEventListener('click', () => {
                formCategoria.reset();
                document.getElementById('catId').value = '';
                document.getElementById('tituloModalCategoria').textContent = 'Nova Categoria';
                abrirModal('modalCategoria');
            });

            document.querySelectorAll('.btn-editar-categoria').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('catId').value = btn.dataset.id;
                    document.getElementById('catNome').value = btn.dataset.nome;
                    document.getElementById('catOrdem').value = btn.dataset.ordem;
                    document.getElementById('tituloModalCategoria').textContent = 'Editar Categoria';
                    abrirModal('modalCategoria');
                });
            });

            formCategoria.addEventListener('submit', async e => {
                e.preventDefault();
                try {
                    const data = await enviarAcao(new FormData(formCategoria));
                    fecharModal('modalCategoria');
                    mostrarAlerta(data.mensagem, 'success');
                    setTimeout(() => window.location.href = 'produtos.php?aba=categorias', 500);
                } catch (err) {
                    mostrarAlerta(err.message, 'error');
                }
            });

            // Excluir categoria
            document.querySelectorAll('.btn-excluir-categoria').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm(`Deseja realmente excluir a categoria "${btn.dataset.nome}"?`)) return;
                    const fd = new FormData();
                    fd.append('acao', 'excluir_categoria');
                    fd.append('id', btn.dataset.id);
                    try {
                        const data = await enviarAcao(fd);
                        mostrarAlerta(data.mensagem, 'success');
                        btn.closest('tr').remove();
                    } catch (err) {
                        mostrarAlerta(err.message, 'error');
                    }
                });
            });
        })();
    </script>
</body>

</html>
