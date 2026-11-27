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
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-graph-up-arrow me-1"></i> Dashboard BI</a>
                    </li>
                </ul>
                <div class="d-flex gap-2 align-items-center">
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
