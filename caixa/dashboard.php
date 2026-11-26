<?php

declare(strict_types=1);

session_start();

// require_once __DIR__ . '/../config/db.php';
require_once '../config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Conexão com o banco de dados ($pdo) não encontrada.');
}

// Filtro de período
$hoje = new DateTimeImmutable('today');
$periodo = $_GET['periodo'] ?? '7d';

$avisoData = null;
function lerData(?string $valor, DateTimeImmutable $padrao, ?string &$aviso): DateTimeImmutable
{
    if ($valor === null || $valor === '') return $padrao;
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    $erros = DateTimeImmutable::getLastErrors();
    if ($data === false || ($erros && ($erros['warning_count'] || $erros['error_count']))) {
        $aviso = 'Data inválida informada no filtro. Foi usado o período padrão.';
        return $padrao;
    }
    return $data;
}

$dataFim = $periodo === 'custom' ? lerData($_GET['data_fim'] ?? null, $hoje, $avisoData) : $hoje;
$dataInicio = match ($periodo) {
    'hoje' => $hoje,
    '30d' => $hoje->modify('-29 days'),
    'custom' => lerData($_GET['data_inicio'] ?? null, $hoje->modify('-6 days'), $avisoData),
    default => $hoje->modify('-6 days'), // 7d
};

if ($dataInicio > $dataFim) {
    [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
}

if ($dataInicio < $dataFim->modify('-365 days')) {
    $dataInicio = $dataFim->modify('-365 days');
    $avisoData = 'O período máximo é de 366 dias. A data inicial foi ajustada.';
}

$dtInicioSql = $dataInicio->format('Y-m-d 00:00:00');
$dtFimSql = $dataFim->format('Y-m-d 23:59:59');

// Faturamento considera apenas contas fechadas, pela data de fechamento
// 1. KPIs gerais: Faturamento e Total de Pedidos
$stmtResumo = $pdo->prepare(
    "SELECT
        COALESCE(SUM(total), 0) AS faturamento_total,
        COUNT(*) AS total_pedidos,
        COALESCE(AVG(total), 0) AS ticket_medio
     FROM pedidos
     WHERE status_conta = 'fechada'
       AND data_fechamento BETWEEN :inicio AND :fim"
);
$stmtResumo->execute([':inicio' => $dtInicioSql, ':fim' => $dtFimSql]);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [
    'faturamento_total' => 0,
    'total_pedidos' => 0,
    'ticket_medio' => 0,
];

// 2. KPI Tempo Médio de Entrega (em minutos): do pedido do item até a entrega na mesa
$stmtEntrega = $pdo->prepare(
    "SELECT
        COALESCE(AVG(TIMESTAMPDIFF(MINUTE, timestamp_pedido, timestamp_entrega)), 0) AS tempo_medio_min,
        COUNT(*) AS total_entregas
     FROM itens_pedido
     WHERE timestamp_pedido BETWEEN :inicio AND :fim
       AND timestamp_entrega IS NOT NULL
       AND timestamp_entrega >= timestamp_pedido"
);
$stmtEntrega->execute([':inicio' => $dtInicioSql, ':fim' => $dtFimSql]);
$entregaResumo = $stmtEntrega->fetch(PDO::FETCH_ASSOC) ?: [
    'tempo_medio_min' => 0,
    'total_entregas' => 0,
];

// 3. Série diária de Faturamento e Tempo Médio de Entrega
$linhasDiarias = [];

$stmtFaturamentoDiario = $pdo->prepare(
    "SELECT DATE(data_fechamento) AS dia, COALESCE(SUM(total), 0) AS faturamento_dia
     FROM pedidos
     WHERE status_conta = 'fechada'
       AND data_fechamento BETWEEN :inicio AND :fim
     GROUP BY DATE(data_fechamento)"
);
$stmtFaturamentoDiario->execute([':inicio' => $dtInicioSql, ':fim' => $dtFimSql]);
foreach ($stmtFaturamentoDiario->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $linhasDiarias[$row['dia']]['faturamento'] = round((float) $row['faturamento_dia'], 2);
}

$stmtTempoDiario = $pdo->prepare(
    "SELECT DATE(timestamp_pedido) AS dia,
            AVG(TIMESTAMPDIFF(MINUTE, timestamp_pedido, timestamp_entrega)) AS tempo_medio_dia
     FROM itens_pedido
     WHERE timestamp_pedido BETWEEN :inicio AND :fim
       AND timestamp_entrega IS NOT NULL
       AND timestamp_entrega >= timestamp_pedido
     GROUP BY DATE(timestamp_pedido)"
);
$stmtTempoDiario->execute([':inicio' => $dtInicioSql, ':fim' => $dtFimSql]);
foreach ($stmtTempoDiario->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $linhasDiarias[$row['dia']]['tempo_medio'] = round((float) $row['tempo_medio_dia'], 1);
}

// Preenche todos os dias do intervalo para não haver buracos no gráfico
$labelsDias = [];
$serieFaturamento = [];
$serieTempoEntrega = [];

$cursor = $dataInicio;
while ($cursor <= $dataFim) {
    $chaveDia = $cursor->format('Y-m-d');
    $labelsDias[] = $cursor->format('d/m');
    $serieFaturamento[] = $linhasDiarias[$chaveDia]['faturamento'] ?? 0.0;
    $serieTempoEntrega[] = $linhasDiarias[$chaveDia]['tempo_medio'] ?? 0.0;
    $cursor = $cursor->modify('+1 day');
}

// 4. Produtos Mais Vendidos (Top 10)
$stmtMaisVendidos = $pdo->prepare(
    "SELECT
        COALESCE(pr.nome, 'Item sem nome') AS produto_nome,
        COALESCE(SUM(ip.quantidade), 0) AS quantidade_vendida,
        COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS receita_gerada
     FROM itens_pedido ip
     INNER JOIN pedidos p ON p.id = ip.id_pedido
     LEFT JOIN produtos pr ON pr.id = ip.id_produto
     WHERE p.status_conta = 'fechada'
       AND p.data_fechamento BETWEEN :inicio AND :fim
     GROUP BY ip.id_produto, produto_nome
     ORDER BY quantidade_vendida DESC, receita_gerada DESC
     LIMIT 10"
);
$stmtMaisVendidos->execute([':inicio' => $dtInicioSql, ':fim' => $dtFimSql]);
$maisVendidos = $stmtMaisVendidos->fetchAll(PDO::FETCH_ASSOC);

$labelsTopProdutos = array_map(
    static fn(array $item): string => (string) $item['produto_nome'],
    $maisVendidos
);
$valoresTopProdutos = array_map(
    static fn(array $item): int => (int) $item['quantidade_vendida'],
    $maisVendidos
);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard BI — Caixa</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg: #f4f6f9;
            --surface: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #0f766e;
            --primary-hover: #115e59;
            --accent: #2563eb;
            --warning: #d97706;
            --radius: 12px;
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
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 16px 48px;
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
            font-size: 1.65rem;
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
            padding: 9px 15px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-outline {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border);
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .quick-periods {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .custom-range {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .input {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.88rem;
            background: #fff;
            color: var(--text);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }

        .kpi-card.blue {
            border-left-color: var(--accent);
        }

        .kpi-card.amber {
            border-left-color: var(--warning);
        }

        .kpi-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            font-weight: 600;
        }

        .kpi-value {
            font-size: 1.7rem;
            font-weight: 700;
            margin-top: 6px;
        }

        .kpi-sub {
            font-size: 0.82rem;
            color: var(--muted);
            margin-top: 4px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .chart-card.full {
            grid-column: 1 / -1;
        }

        .chart-header {
            margin-bottom: 12px;
        }

        .chart-header h2 {
            font-size: 1.08rem;
            font-weight: 700;
        }

        .chart-header p {
            font-size: 0.84rem;
            color: var(--muted);
        }

        .canvas-box {
            position: relative;
            height: 290px;
        }

        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-card-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
        }

        .table-card-header h2 {
            font-size: 1.08rem;
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
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        @media (max-width: 900px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="topbar">
            <div>
                <h1>Dashboard BI — Caixa</h1>
                <p>
                    Período analisado:
                    <strong><?= $dataInicio->format('d/m/Y') ?></strong> até <strong><?= $dataFim->format('d/m/Y') ?></strong>
                </p>
                <?php if ($avisoData): ?>
                    <p style="color:#b42318; font-weight:600;"><?= htmlspecialchars($avisoData) ?></p>
                <?php endif; ?>
            </div>
            <div class="nav-links">
                <a href="produtos.php" class="btn btn-outline">Produtos e Categorias</a>
                <a href="index.php" class="btn btn-outline">Voltar ao Caixa</a>
            </div>
        </div>

        <div class="filter-bar">
            <div class="quick-periods">
                <a href="?periodo=hoje" class="btn <?= $periodo === 'hoje' ? 'btn-primary' : 'btn-outline' ?>">Hoje</a>
                <a href="?periodo=7d" class="btn <?= $periodo === '7d' ? 'btn-primary' : 'btn-outline' ?>">Últimos 7 dias</a>
                <a href="?periodo=30d" class="btn <?= $periodo === '30d' ? 'btn-primary' : 'btn-outline' ?>">Últimos 30 dias</a>
            </div>

            <form method="GET" class="custom-range">
                <input type="hidden" name="periodo" value="custom">
                <label for="data_inicio" style="font-size:0.85rem; color:var(--muted);">De:</label>
                <input type="date" id="data_inicio" name="data_inicio" class="input" value="<?= $dataInicio->format('Y-m-d') ?>">
                <label for="data_fim" style="font-size:0.85rem; color:var(--muted);">Até:</label>
                <input type="date" id="data_fim" name="data_fim" class="input" value="<?= $dataFim->format('Y-m-d') ?>">
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </form>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Faturamento no Período</div>
                <div class="kpi-value">R$ <?= number_format((float) $resumo['faturamento_total'], 2, ',', '.') ?></div>
                <div class="kpi-sub"><?= (int) $resumo['total_pedidos'] ?> contas fechadas</div>
            </div>

            <div class="kpi-card blue">
                <div class="kpi-title">Ticket Médio</div>
                <div class="kpi-value">R$ <?= number_format((float) $resumo['ticket_medio'], 2, ',', '.') ?></div>
                <div class="kpi-sub">Média por pedido no período</div>
            </div>

            <div class="kpi-card amber">
                <div class="kpi-title">Tempo Médio de Entrega</div>
                <div class="kpi-value"><?= number_format((float) $entregaResumo['tempo_medio_min'], 1, ',', '.') ?> min</div>
                <div class="kpi-sub"><?= (int) $entregaResumo['total_entregas'] ?> itens entregues</div>
            </div>
        </div>

        <!-- GRÁFICOS CHART.JS -->
        <div class="charts-grid">
            <div class="chart-card full">
                <div class="chart-header">
                    <h2>Faturamento Diário (R$)</h2>
                    <p>Evolução da receita por dia no intervalo selecionado</p>
                </div>
                <div class="canvas-box">
                    <canvas id="graficoFaturamento"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h2>Produtos Mais Vendidos (Unidades)</h2>
                    <p>Top 10 itens com maior volume de saída</p>
                </div>
                <div class="canvas-box">
                    <canvas id="graficoMaisVendidos"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h2>Tempo Médio de Entrega Diário (Minutos)</h2>
                    <p>Intervalo médio entre criação e entrega do pedido</p>
                </div>
                <div class="canvas-box">
                    <canvas id="graficoTempoEntrega"></canvas>
                </div>
            </div>
        </div>

        <!-- RANKING DETALHADO DE MAIS VENDIDOS -->
        <div class="table-card">
            <div class="table-card-header">
                <h2>Detalhamento dos Mais Vendidos</h2>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th>Quantidade Vendida</th>
                            <th>Receita Gerada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maisVendidos)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--muted); padding:26px;">
                                    Nenhuma venda registrada no período selecionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($maisVendidos as $idx => $item): ?>
                                <tr>
                                    <td><?= $idx + 1 ?>º</td>
                                    <td><strong><?= htmlspecialchars((string) $item['produto_nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= (int) $item['quantidade_vendida'] ?> un.</td>
                                    <td>R$ <?= number_format((float) $item['receita_gerada'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const labelsDias = <?= json_encode($labelsDias, JSON_UNESCAPED_UNICODE) ?>;
            const serieFaturamento = <?= json_encode($serieFaturamento, JSON_NUMERIC_CHECK) ?>;
            const serieTempoEntrega = <?= json_encode($serieTempoEntrega, JSON_NUMERIC_CHECK) ?>;
            const labelsTopProdutos = <?= json_encode($labelsTopProdutos, JSON_UNESCAPED_UNICODE) ?>;
            const valoresTopProdutos = <?= json_encode($valoresTopProdutos, JSON_NUMERIC_CHECK) ?>;

            // 1. Gráfico Faturamento Diário
            new Chart(document.getElementById('graficoFaturamento'), {
                type: 'bar',
                data: {
                    labels: labelsDias,
                    datasets: [{
                        label: 'Faturamento (R$)',
                        data: serieFaturamento,
                        backgroundColor: 'rgba(15, 118, 110, 0.78)',
                        borderColor: '#0f766e',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `R$ ${Number(ctx.parsed.y || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: val => `R$ ${Number(val).toLocaleString('pt-BR')}`
                            }
                        }
                    }
                }
            });

            // 2. Gráfico Produtos Mais Vendidos
            new Chart(document.getElementById('graficoMaisVendidos'), {
                type: 'bar',
                data: {
                    labels: labelsTopProdutos,
                    datasets: [{
                        label: 'Unidades Vendidas',
                        data: valoresTopProdutos,
                        backgroundColor: 'rgba(37, 99, 235, 0.78)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // 3. Gráfico Tempo Médio de Entrega Diário
            new Chart(document.getElementById('graficoTempoEntrega'), {
                type: 'line',
                data: {
                    labels: labelsDias,
                    datasets: [{
                        label: 'Tempo Médio (min)',
                        data: serieTempoEntrega,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.15)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${Number(ctx.parsed.y || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} min`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: val => `${val} min`
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>

</html>