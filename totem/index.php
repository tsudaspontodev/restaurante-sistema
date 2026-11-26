<?php
// totem/index.php — Autoatendimento
require_once '../config/db.php';

$categorias = $pdo->query("
    SELECT c.id, c.nome
    FROM categorias c
    WHERE EXISTS (SELECT 1 FROM produtos p WHERE p.id_categoria = c.id AND p.ativo = 1)
    ORDER BY c.ordem ASC, c.nome ASC
")->fetchAll();

$produtos = $pdo->query("
    SELECT id, id_categoria, nome, descricao, preco, imagem_url
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Totem de Autoatendimento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/totem.css">
</head>

<body class="totem">

    <!-- ETAPA 1: Boas-vindas / Tipo de consumo -->
    <section id="etapaInicio" class="totem-etapa totem-inicio">
        <div class="text-center px-3">
            <i class="bi bi-shop display-1 text-warning"></i>
            <h1 class="fw-bold mt-3 mb-1">Bem-vindo!</h1>
            <p class="fs-4 text-white-50 mb-5">Faça seu pedido aqui. Onde você vai comer?</p>
            <div class="d-flex flex-column flex-md-row gap-4 justify-content-center">
                <button type="button" class="btn btn-light totem-opcao" data-consumo="local">
                    <i class="bi bi-cup-hot"></i>
                    <span>Comer aqui</span>
                </button>
                <button type="button" class="btn btn-light totem-opcao" data-consumo="viagem">
                    <i class="bi bi-bag-check"></i>
                    <span>Para viagem</span>
                </button>
            </div>
        </div>
    </section>

    <!-- ETAPA 2: Cardápio + Carrinho -->
    <section id="etapaCardapio" class="totem-etapa d-none">
        <header class="totem-header d-flex justify-content-between align-items-center px-4">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-light btn-touch" id="btnCancelar">
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                </button>
                <h4 class="mb-0 fw-bold"><i class="bi bi-shop text-warning me-2"></i>Cardápio</h4>
            </div>
            <button type="button" class="btn btn-warning btn-touch fw-semibold" id="btnTrocarConsumo">
                <i class="bi bi-arrow-left-right me-1"></i> <span id="labelConsumo"></span>
            </button>
        </header>

        <div class="totem-corpo">
            <nav class="totem-categorias" id="listaCategorias">
                <?php foreach ($categorias as $i => $cat): ?>
                    <button type="button" class="btn totem-categoria <?= $i === 0 ? 'active' : '' ?>" data-categoria="<?= (int) $cat['id'] ?>">
                        <?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endforeach; ?>
            </nav>

            <main class="totem-produtos">
                <?php if (empty($produtos)): ?>
                    <div class="alert alert-warning m-4">Nenhum produto disponível no momento.</div>
                <?php endif; ?>
                <div class="row g-3" id="gridProdutos"></div>
            </main>

            <aside class="totem-carrinho">
                <h5 class="fw-bold p-3 mb-0 border-bottom"><i class="bi bi-cart3 me-2"></i>Seu pedido</h5>
                <div class="totem-carrinho-itens" id="carrinhoItens"></div>
                <div class="p-3 border-top bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-5">Total</span>
                        <span class="fs-3 fw-bold" id="carrinhoTotal">R$ 0,00</span>
                    </div>
                    <button type="button" class="btn btn-success btn-touch w-100 py-3 fs-5 fw-bold" id="btnIrPagamento" disabled>
                        Ir para pagamento <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </aside>
        </div>
    </section>

    <!-- ETAPA 3: Pagamento -->
    <section id="etapaPagamento" class="totem-etapa d-none">
        <header class="totem-header d-flex align-items-center px-4">
            <button type="button" class="btn btn-outline-light btn-touch" id="btnVoltarCardapio">
                <i class="bi bi-arrow-left me-1"></i> Voltar ao cardápio
            </button>
            <h4 class="mb-0 fw-bold ms-3">Fechamento e pagamento</h4>
        </header>
        <div class="container py-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Resumo do pedido</h5>
                            <p class="mb-3">
                                <span class="badge bg-dark fs-6" id="resumoConsumo"></span>
                            </p>
                            <ul class="list-group list-group-flush mb-3" id="resumoItens"></ul>
                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <span class="fs-4">Total a pagar</span>
                                <span class="fs-2 fw-bold text-success" id="resumoTotal"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="fw-bold mb-3">Como você quer pagar?</h5>
                            <div class="row g-3 mb-4" id="opcoesPagamento">
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-dark totem-pagamento w-100" data-pagamento="Cartão Crédito">
                                        <i class="bi bi-credit-card"></i><span>Crédito</span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-dark totem-pagamento w-100" data-pagamento="Cartão Débito">
                                        <i class="bi bi-credit-card-2-front"></i><span>Débito</span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-dark totem-pagamento w-100" data-pagamento="PIX">
                                        <i class="bi bi-qr-code"></i><span>PIX</span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-dark totem-pagamento w-100" data-pagamento="Dinheiro">
                                        <i class="bi bi-cash-coin"></i><span>Dinheiro</span>
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info d-none" id="instrucaoPagamento"></div>
                            <div class="alert alert-danger d-none" id="erroPagamento"></div>
                            <button type="button" class="btn btn-success btn-touch w-100 py-3 fs-4 fw-bold mt-auto" id="btnConfirmarPagamento" disabled>
                                <i class="bi bi-check2-circle me-1"></i> Confirmar pagamento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ETAPA 4: Confirmação -->
    <section id="etapaConfirmacao" class="totem-etapa totem-inicio d-none">
        <div class="text-center px-3">
            <i class="bi bi-check-circle-fill display-1 text-success"></i>
            <h1 class="fw-bold mt-3" id="confirmacaoTitulo">Pedido confirmado!</h1>
            <p class="fs-4 text-white-50 mb-2">Acompanhe sua senha no painel</p>
            <div class="totem-senha" id="confirmacaoSenha">000</div>
            <p class="fs-5 mb-1" id="confirmacaoDetalhe"></p>
            <div class="alert alert-warning fs-5 fw-semibold mx-auto mt-3 d-none" id="confirmacaoAviso" style="max-width: 560px;"></div>
            <p class="text-white-50 mb-4">Esta tela volta ao início em <span id="contagemReinicio">15</span>s</p>
            <button type="button" class="btn btn-warning btn-touch px-5 fw-bold" id="btnNovoPedido">Fazer novo pedido</button>
        </div>
    </section>

    <!-- Modal: adicionar produto -->
    <div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <img src="" alt="" class="totem-modal-img" id="modalProdutoImg">
                <div class="modal-body">
                    <h4 class="fw-bold mb-1" id="modalProdutoNome"></h4>
                    <p class="text-muted" id="modalProdutoDescricao"></p>
                    <label for="modalProdutoObs" class="form-label fw-semibold">Observação (opcional)</label>
                    <input type="text" class="form-control form-control-lg mb-3" id="modalProdutoObs" maxlength="255" placeholder="Ex.: sem cebola">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-touch px-4" id="modalProdutoMenos"><i class="bi bi-dash-lg"></i></button>
                            <span class="btn btn-light btn-touch px-4 fw-bold fs-5" id="modalProdutoQtd">1</span>
                            <button type="button" class="btn btn-outline-secondary btn-touch px-4" id="modalProdutoMais"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <span class="fs-4 fw-bold" id="modalProdutoSubtotal"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-touch" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success btn-touch fw-bold" id="modalProdutoAdicionar">
                        <i class="bi bi-cart-plus me-1"></i> Adicionar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const TOTEM_CATEGORIAS = <?= json_encode($categorias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        const TOTEM_PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/totem.js"></script>
</body>

</html>
