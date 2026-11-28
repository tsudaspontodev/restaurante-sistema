<?php
$paginaAtiva = $paginaAtiva ?? '';
$mesasNav = $mesasNav ?? [];
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="produtos.php">
            <i class="bi bi-display me-2 text-warning"></i>GERÊNCIA &amp; PDV
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarGerencia" aria-controls="navbarGerencia" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarGerencia">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-grid-3x3-gap me-1"></i> Mapa de Mesas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $paginaAtiva === 'produtos' ? 'active fw-semibold' : '' ?>" <?= $paginaAtiva === 'produtos' ? 'aria-current="page"' : '' ?> href="produtos.php"><i class="bi bi-box-seam me-1"></i> Produtos &amp; Cardápio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $paginaAtiva === 'clientes' ? 'active fw-semibold' : '' ?>" <?= $paginaAtiva === 'clientes' ? 'aria-current="page"' : '' ?> href="clientes.php"><i class="bi bi-person-lines-fill me-1"></i> Clientes / Delivery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $paginaAtiva === 'dashboard' ? 'active fw-semibold' : '' ?>" <?= $paginaAtiva === 'dashboard' ? 'aria-current="page"' : '' ?> href="dashboard.php"><i class="bi bi-graph-up-arrow me-1"></i> Dashboard BI</a>
                </li>
            </ul>
            <div class="d-flex flex-column flex-lg-row gap-2 align-items-stretch align-items-lg-center">
                <a href="../totem/index.php" target="_blank" rel="noopener" class="btn btn-outline-light btn-sm">Abrir Totem</a>
                <div class="dropdown">
                    <button class="btn btn-outline-warning btn-sm dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">Simular Tablet (Mesa)</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($mesasNav as $mesaNav): ?>
                            <li><a class="dropdown-item" href="../tablet/index.php?mesa=<?= (int)$mesaNav['numero'] ?>" target="_blank" rel="noopener">Mesa <?= str_pad((string)$mesaNav['numero'], 2, '0', STR_PAD_LEFT) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (!$mesasNav): ?><li><span class="dropdown-item-text text-muted">Nenhuma mesa cadastrada</span></li><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
