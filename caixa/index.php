<?php
// caixa/index.php
require_once '../config/db.php';

// Consulta todas as mesas + contagem de itens aguardando na cozinha + tempo máximo de espera
$sqlMesas = "
    SELECT 
        m.id,
        m.numero,
        m.status,
        p.id AS id_pedido,
        (SELECT COUNT(*) FROM itens_pedido ip WHERE ip.id_pedido = p.id AND ip.status_item = 'na_cozinha') AS qtd_cozinha,
        (SELECT MAX(TIMESTAMPDIFF(MINUTE, ip.timestamp_pedido, NOW())) FROM itens_pedido ip WHERE ip.id_pedido = p.id AND ip.status_item = 'na_cozinha') AS max_espera,
        (SELECT COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) FROM itens_pedido ip WHERE ip.id_pedido = p.id) AS valor_parcial
    FROM mesas m
    LEFT JOIN pedidos p ON p.id_mesa = m.id AND p.status_conta = 'aberta'
    ORDER BY m.numero ASC
";
$mesas = $pdo->query($sqlMesas)->fetchAll();
$produtosAtivos = $pdo->query("SELECT id, nome, preco FROM produtos WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDV & Gerência - Mapa de Mesas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

    <!-- Navbar Superior do Caixa -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-display me-2 text-warning"></i>GERÊNCIA & PDV
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="index.php"><i class="bi bi-grid-3x3-gap me-1"></i> Mapa de Mesas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="produtos.php"><i class="bi bi-box-seam me-1"></i> Produtos & Cardápio</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="clientes.php"><i class="bi bi-person-lines-fill me-1"></i> Clientes / Delivery</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-graph-up-arrow me-1"></i> Dashboard BI</a>
                    </li>
                </ul>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-warning btn-sm fw-semibold" type="button" onclick="abrirNovoDelivery()"><i class="bi bi-bicycle me-1"></i>+ Novo Pedido Delivery</button>
                    <a href="../totem/index.php" target="_blank" class="btn btn-outline-light btn-sm">Abrir Totem</a>
                    <div class="dropdown">
                        <button class="btn btn-outline-warning btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Simular Tablet (Mesa)</button>
                        <ul class="dropdown-menu dropdown-menu-end" id="seletorMesasTablet">
                            <?php foreach ($mesas as $mesa):
                                $statusTablet = ['livre' => 'Livre', 'ocupada' => 'Ocupada', 'fechamento' => 'Fechamento'][$mesa['status']] ?? $mesa['status'];
                                $corTablet = ['livre' => 'text-success', 'ocupada' => 'text-danger', 'fechamento' => 'text-warning-emphasis'][$mesa['status']] ?? 'text-secondary';
                            ?>
                                <li><a class="dropdown-item d-flex justify-content-between align-items-center" data-tablet-mesa="<?= (int)$mesa['id'] ?>" href="../tablet/index.php?mesa=<?= (int)$mesa['numero'] ?>" target="_blank" rel="noopener">
                                    <span>Mesa <?= str_pad($mesa['numero'], 2, '0', STR_PAD_LEFT) ?></span><small class="<?= $corTablet ?> mesa-tablet-status"><?= htmlspecialchars($statusTablet, ENT_QUOTES, 'UTF-8') ?></small>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold">Controle Operacional de Mesas</h4>
                <small class="text-muted">Clique em uma mesa para gerenciar pedidos da cozinha ou fechar a conta</small>
            </div>
            <!-- Legenda de Status -->
            <div class="d-flex gap-3 mt-2 mt-md-0">
                <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i> Livre</span>
                <span class="badge bg-danger px-3 py-2"><i class="bi bi-people-fill me-1"></i> Ocupada</span>
                <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-receipt me-1"></i> Pediu a Conta</span>
            </div>
        </div>

        <!-- Pedidos do Totem (não têm mesa) -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-phone me-2 text-primary"></i>Pedidos do Totem
                    <span class="badge bg-primary ms-1" id="pedidosTotemQtd">0</span></h5>
                <small class="text-muted">Pagamentos em dinheiro pendentes e itens aguardando entrega</small>
            </div>
            <div class="card-body" id="pedidosTotem">
                <div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-bicycle me-2 text-success"></i>Pedidos Delivery em Andamento <span class="badge bg-success ms-1" id="pedidosDeliveryQtd">0</span></h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="carregarPedidosDelivery()" aria-label="Atualizar pedidos delivery"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body" id="pedidosDelivery"><div class="text-center py-2"><div class="spinner-border spinner-border-sm text-success"></div></div></div>
        </div>

        <!-- Grid Responsivo de Mesas -->
        <div class="row g-3">
            <?php foreach ($mesas as $mesa):
                // Define classes visuais conforme status
                $cardClass = 'mesa-livre border-success';
                $badgeClass = 'bg-success';
                $statusTexto = 'LIVRE';

                if ($mesa['status'] === 'ocupada') {
                    $cardClass = 'mesa-ocupada border-danger';
                    $badgeClass = 'bg-danger';
                    $statusTexto = 'OCUPADA';
                } elseif ($mesa['status'] === 'fechamento') {
                    $cardClass = 'mesa-fechamento border-warning';
                    $badgeClass = 'bg-warning text-dark';
                    $statusTexto = 'FECHAMENTO';
                }
            ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-2 mesa-card <?= $cardClass ?>"
                        data-mesa-id="<?= (int)$mesa['id'] ?>" onclick="abrirModalMesa(<?= $mesa['id'] ?>)" style="cursor: pointer;">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-start">
                                <h3 class="fw-bold mb-0">Mesa <?= str_pad($mesa['numero'], 2, '0', STR_PAD_LEFT) ?></h3>
                                <span class="badge <?= $badgeClass ?> mesa-status-label"><?= $statusTexto ?></span>
                            </div>

                            <div class="my-3 mesa-info" data-status="<?= htmlspecialchars($mesa['status'], ENT_QUOTES, 'UTF-8') ?>" data-valor="<?= (float)$mesa['valor_parcial'] ?>" data-cozinha="<?= (int)$mesa['qtd_cozinha'] ?>" data-id-pedido="<?= (int)($mesa['id_pedido'] ?? 0) ?>">
                                <p class="text-muted small mb-0 mesa-livre-texto <?= $mesa['status'] === 'livre' ? '' : 'd-none' ?>">Disponível para clientes</p>
                                <div class="fs-5 fw-bold text-dark mesa-valor <?= $mesa['status'] === 'livre' ? 'd-none' : '' ?>">R$ <?= number_format($mesa['valor_parcial'], 2, ',', '.') ?></div>
                                <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small d-flex justify-content-between align-items-center mesa-cozinha <?= $mesa['qtd_cozinha'] > 0 && $mesa['status'] !== 'livre' ? '' : 'd-none' ?>">
                                    <span><i class="bi bi-fire me-1"></i> <strong class="mesa-qtd-cozinha"><?= (int)$mesa['qtd_cozinha'] ?></strong> na cozinha</span>
                                    <span class="badge bg-danger"><span class="mesa-max-espera"><?= (int)($mesa['max_espera'] ?? 0) ?></span> min</span>
                                </div>
                                <small class="text-success mesa-entregues <?= (int)$mesa['qtd_cozinha'] === 0 && $mesa['status'] !== 'livre' ? '' : 'd-none' ?>"><i class="bi bi-check2-all"></i> Todos os itens entregues</small>
                            </div>

                            <div class="text-end">
                                <small class="text-primary fw-semibold">Gerenciar <i class="bi bi-arrow-right"></i></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="modalNovoDelivery" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="formNovoDelivery">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-bicycle me-2"></i>Novo Pedido Delivery</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="row g-4">
                <section class="col-lg-5"><h6 class="fw-bold">1. Cliente</h6>
                    <label class="form-label" for="buscaClienteDelivery">Buscar por telefone ou nome</label><input class="form-control" id="buscaClienteDelivery" autocomplete="off" placeholder="Digite nome ou WhatsApp">
                    <div class="list-group mt-2 d-none" id="resultadosClienteDelivery"></div>
                    <button class="btn btn-sm btn-outline-primary mt-3" type="button" id="toggleNovoCliente"><i class="bi bi-person-plus me-1"></i>Cadastrar Novo Cliente</button>
                    <div id="formDadosClienteDelivery" class="mt-3 d-none"><div class="row g-2">
                    <?php foreach ([['nome','Nome completo',true],['telefone','Telefone / WhatsApp',true],['cep','CEP',false],['logradouro','Logradouro',true],['numero','Número',true],['complemento','Complemento',false],['bairro','Bairro',true],['cidade','Cidade',false],['ponto_referencia','Ponto de referência',false]] as [$campo,$rotulo,$obrigatorio]): ?><div class="col-<?= in_array($campo,['logradouro'])?'12':'6' ?>"><label class="form-label small mb-1" for="delivery_<?= $campo ?>"><?= $rotulo ?></label><?php if($campo==='cep'): ?><div class="input-group input-group-sm"><input class="form-control" id="delivery_cep" maxlength="9" inputmode="numeric" autocomplete="postal-code"><span class="input-group-text d-none" id="delivery_cep_spinner"><span class="spinner-border spinner-border-sm" role="status"></span></span></div><div class="small text-danger d-none" id="delivery_cep_feedback"></div><?php else: ?><input class="form-control form-control-sm" id="delivery_<?= $campo ?>"><?php endif; ?></div><?php endforeach; ?>
                    </div><div class="small text-muted mt-2" id="clienteDeliveryStatus">Selecione um cliente ou preencha os dados para cadastrar.</div></div>
                </section>
                <section class="col-lg-7"><h6 class="fw-bold">2. Itens e pagamento</h6><div class="row g-2 align-items-end"><div class="col"><label class="form-label" for="produtoDelivery">Produto</label><select class="form-select" id="produtoDelivery"><option value="">Selecione um produto</option><?php foreach ($produtosAtivos as $produto): ?><option value="<?= (int)$produto['id'] ?>" data-preco="<?= (float)$produto['preco'] ?>"><?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?> — R$ <?= number_format((float)$produto['preco'],2,',','.') ?></option><?php endforeach; ?></select></div><div class="col-4 col-md-3"><label class="form-label" for="quantidadeDelivery">Qtd.</label><input class="form-control" id="quantidadeDelivery" type="number" min="1" max="50" value="1"></div><div class="col-auto"><button class="btn btn-primary" type="button" id="adicionarItemDelivery"><i class="bi bi-plus-lg"></i></button></div></div>
                    <div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>Produto</th><th style="width:100px">Qtd.</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody id="carrinhoDelivery"><tr><td colspan="4" class="text-muted text-center">Nenhum produto adicionado.</td></tr></tbody></table></div>
                    <div class="row g-2"><div class="col-sm-4"><label class="form-label" for="taxaDelivery">Taxa de entrega (R$)</label><input class="form-control" id="taxaDelivery" type="number" min="0" step="0.01" value="0"></div><div class="col-sm-4"><label class="form-label" for="pagamentoDelivery">Forma de pagamento</label><select class="form-select" id="pagamentoDelivery"><option>Dinheiro</option><option>Pix</option><option>Cartão na Entrega</option></select></div><div class="col-sm-4" id="campoTrocoDelivery"><label class="form-label" for="trocoDelivery">Troco para (R$)</label><input class="form-control" id="trocoDelivery" type="number" min="0" step="0.01" placeholder="Opcional"></div></div>
                    <div class="text-end mt-3"><span class="text-muted me-2">Subtotal: <strong id="subtotalDelivery">R$ 0,00</strong></span><span class="fs-5 fw-bold">Total: <span id="totalGeralDelivery">R$ 0,00</span></span></div>
                </section>
            </div><div class="alert alert-danger d-none mt-3" id="erroNovoDelivery"></div></div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success fw-bold" id="confirmarDelivery" type="submit"><i class="bi bi-check-circle me-1"></i>Confirmar Pedido Delivery</button></div>
        </form></div>
    </div>

    <!-- Modal de Detalhes da Mesa -->
    <div class="modal fade" id="modalMesa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalMesaTitulo">Detalhes da Mesa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalMesaCorpo">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between" id="modalMesaRodape">
                    <!-- Preenchido dinamicamente via caixa.js -->
                </div>
            </div>
        </div>
    </div>

    <script>const CAIXA_PRODUTOS = <?= json_encode($produtosAtivos, $jsonFlags) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/caixa.js"></script>
</body>

</html>
