<?php
// tablet/index.php — Cardápio digital da mesa (Home)
require_once '../config/db.php';

$mesa = null;
$statusMesa = null;
$numeroMesa = filter_input(INPUT_GET, 'mesa', FILTER_VALIDATE_INT);
if ($numeroMesa) {
    $stmtMesa = $pdo->prepare("SELECT id, numero, status FROM mesas WHERE numero = ?");
    $stmtMesa->execute([$numeroMesa]);
    $mesa = $stmtMesa->fetch() ?: null;
    $statusMesa = $mesa['status'] ?? null;
}

$produtos = $pdo->query("
    SELECT p.id, p.id_categoria, p.nome, p.descricao, p.preco, p.imagem_url,
           COALESCE(v.vendidos, 0) AS vendidos
    FROM produtos p
    JOIN categorias c ON c.id = p.id_categoria
    LEFT JOIN (SELECT id_produto, SUM(quantidade) AS vendidos FROM itens_pedido GROUP BY id_produto) v
           ON v.id_produto = p.id
    WHERE p.ativo = 1
    ORDER BY c.ordem ASC, p.nome ASC
")->fetchAll();

$categorias = $pdo->query("
    SELECT c.id, c.nome
    FROM categorias c
    WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.id_categoria = c.id AND p.ativo = 1)
    ORDER BY c.ordem ASC, c.nome ASC
")->fetchAll();

$temFoto = fn(?string $url): bool => $url && preg_match('#^https?://#i', $url) && stripos($url, 'placehold.co') === false;

// Miniatura e descrição de cada categoria vêm dos seus produtos
foreach ($categorias as &$cat) {
    $itens = array_values(array_filter($produtos, fn($p) => $p['id_categoria'] == $cat['id']));
    usort($itens, fn($a, $b) => $b['vendidos'] <=> $a['vendidos']);
    $comFoto = array_values(array_filter($itens, fn($p) => $temFoto($p['imagem_url'])));
    $cat['imagem_url'] = $comFoto[0]['imagem_url'] ?? null;
    $cat['qtd'] = count($itens);
    $cat['preco_min'] = min(array_column($itens, 'preco'));
    $nomes = array_slice(array_column($itens, 'nome'), 0, 2);
    $cat['descricao'] = implode(', ', $nomes) . ($cat['qtd'] > 2 ? ' e mais' : '');
}
unset($cat);

// Destaques da casa: mais vendidos (com foto primeiro)
$destaques = $produtos;
usort($destaques, fn($a, $b) => [$temFoto($b['imagem_url']), $b['vendidos']] <=> [$temFoto($a['imagem_url']), $a['vendidos']]);
$destaques = array_column(array_slice($destaques, 0, 8), 'id');

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Cardápio Digital<?= $mesa ? ' — Mesa ' . str_pad($mesa['numero'], 2, '0', STR_PAD_LEFT) : '' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/tablet.css">
</head>

<body class="tablet">
    <div class="tb-app">
        <!-- Menu lateral: categorias -->
        <aside class="tb-sidebar">
            <div class="tb-marca">
                <div class="tb-marca-icone"><i class="bi bi-fire"></i></div>
                <div>
                    <div class="tb-marca-nome">Cardápio Digital</div>
                    <div class="tb-marca-sub">Restaurante &amp; Lanchonete</div>
                </div>
            </div>

            <nav class="tb-categorias" id="listaCategorias" aria-label="Categorias do cardápio"></nav>
        </aside>

        <!-- Conteúdo principal -->
        <main class="tb-main">
            <header class="tb-topo">
                <div>
                    <p class="tb-saudacao mb-1">Olá! O que vai ser hoje?</p>
                    <h1 class="tb-titulo mb-0" id="tituloSecao">Destaques da Casa</h1>
                    <p class="tb-subtitulo mb-0" id="subtituloSecao">Os pratos mais pedidos pelos nossos clientes</p>
                </div>
                <?php if ($mesa): ?>
                    <div class="tb-mesa"><i class="bi bi-geo-alt-fill"></i> Mesa <?= str_pad($mesa['numero'], 2, '0', STR_PAD_LEFT) ?></div>
                <?php else: ?>
                    <div class="tb-mesa tb-mesa-alerta"><i class="bi bi-exclamation-triangle-fill"></i> Mesa não identificada</div>
                <?php endif; ?>
                <?php if ($mesa): ?>
                    <button type="button" class="btn btn-dark" id="btnComanda" data-bs-toggle="offcanvas" data-bs-target="#painelComanda">
                        <i class="bi bi-receipt me-1"></i> Minha Comanda <strong id="comandaTotalTopo">R$ 0,00</strong>
                    </button>
                <?php endif; ?>
            </header>

            <section class="carousel slide tb-destaques d-none" id="carrosselDestaques" aria-label="Destaques da Casa">
                <div class="carousel-inner" id="slidesDestaques"></div>
                <div class="carousel-indicators" id="indicadoresDestaques"></div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carrosselDestaques" data-bs-slide="prev" aria-label="Produto anterior">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carrosselDestaques" data-bs-slide="next" aria-label="Próximo produto">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                </button>
            </section>
            <section class="tb-grid" id="gridProdutos" aria-live="polite"></section>
        </main>
    </div>

    <?php if ($mesa): ?>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="painelComanda" aria-labelledby="painelComandaTitulo">
        <div class="offcanvas-header"><h5 class="offcanvas-title fw-bold" id="painelComandaTitulo">Minha Comanda</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button></div>
        <div class="offcanvas-body" id="comandaCorpo"><p class="text-muted">Carregando comanda...</p></div>
        <div class="border-top p-3 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3"><span class="fw-semibold">Valor Total Consumido</span><strong class="fs-4" id="comandaTotalRodape">R$ 0,00</strong></div>
            <button type="button" class="btn btn-warning w-100 fw-bold" id="btnPedirConta"><i class="bi bi-bell-fill me-1"></i> Fechar a Conta</button>
            <div class="small text-warning-emphasis mt-2 d-none" id="avisoFechamento">O caixa foi avisado. Aguarde o atendimento.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra do pedido -->
    <button type="button" class="tb-carrinho d-none" id="btnCarrinho" data-bs-toggle="offcanvas" data-bs-target="#painelPedido">
        <span class="tb-carrinho-qtd" id="carrinhoQtd">0</span>
        <span class="tb-carrinho-texto">Ver meu pedido</span>
        <span class="tb-carrinho-total" id="carrinhoTotal">R$ 0,00</span>
    </button>

    <!-- Painel do pedido -->
    <div class="offcanvas offcanvas-end tb-painel" tabindex="-1" id="painelPedido" aria-labelledby="painelPedidoTitulo">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="painelPedidoTitulo"><i class="bi bi-bag-heart-fill me-2"></i>Meu pedido</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body" id="painelItens"></div>
        <div class="tb-painel-rodape">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Total</span>
                <span class="tb-painel-total" id="painelTotal">R$ 0,00</span>
            </div>
            <button type="button" class="btn btn-primary w-100 mt-3" id="btnEnviarPedido" <?= !$mesa || $statusMesa === 'fechamento' ? 'disabled' : '' ?>>Enviar pedido à cozinha</button>
        </div>
    </div>

    <div class="tb-toast" id="toast" role="status"></div>

    <div class="tb-girar">
        <i class="bi bi-phone-landscape"></i>
        <p>Gire o tablet para a horizontal</p>
    </div>

    <script>
        const TB_MESA = <?= json_encode($mesa ? (int) $mesa['numero'] : null) ?>;
        const TB_STATUS_MESA = <?= json_encode($statusMesa) ?>;
        const TB_CATEGORIAS = <?= json_encode($categorias, $jsonFlags) ?>;
        const TB_PRODUTOS = <?= json_encode($produtos, $jsonFlags) ?>;
        const TB_DESTAQUES = <?= json_encode($destaques) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/tablet.js"></script>
</body>

</html>
