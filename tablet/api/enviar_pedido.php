<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método não permitido.');
    $dados = json_decode(file_get_contents('php://input'), true);
    $numeroMesa = filter_var($dados['mesa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $itens = $dados['itens'] ?? null;
    if (!$numeroMesa) throw new RuntimeException('Mesa inválida.');
    if (!is_array($itens) || !$itens || count($itens) > 50) throw new RuntimeException('O pedido está vazio ou contém itens demais.');

    $validos = [];
    foreach ($itens as $item) {
        $id = filter_var($item['id_produto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qtd = filter_var($item['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if (!$id || !$qtd) throw new RuntimeException('Item inválido no pedido.');
        $validos[] = [$id, $qtd];
    }

    $pdo->beginTransaction();
    $stmtMesa = $pdo->prepare('SELECT id, status FROM mesas WHERE numero = ? FOR UPDATE');
    $stmtMesa->execute([$numeroMesa]);
    $mesa = $stmtMesa->fetch();
    if (!$mesa) throw new RuntimeException('Mesa não encontrada.');
    if ($mesa['status'] === 'fechamento') throw new RuntimeException('Esta mesa já solicitou o fechamento da conta.');

    $ids = array_column($validos, 0);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtProdutos = $pdo->prepare("SELECT id, preco FROM produtos WHERE ativo = 1 AND id IN ($ph)");
    $stmtProdutos->execute($ids);
    $precos = array_column($stmtProdutos->fetchAll(), 'preco', 'id');
    foreach ($validos as [$id, $qtd]) if (!isset($precos[$id])) throw new RuntimeException('Um produto não está mais disponível.');

    $stmtPedido = $pdo->prepare("SELECT id FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmtPedido->execute([$mesa['id']]);
    $idPedido = $stmtPedido->fetchColumn();
    if (!$idPedido) {
        $pdo->prepare("INSERT INTO pedidos (origem, tipo_consumo, id_mesa, status_conta) VALUES ('tablet', 'local', ?, 'aberta')")->execute([$mesa['id']]);
        $idPedido = (int)$pdo->lastInsertId();
    }
    $insert = $pdo->prepare("INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, status_item) VALUES (?, ?, ?, ?, 'na_cozinha')");
    $totalAdicionado = 0;
    foreach ($validos as [$id, $qtd]) {
        $insert->execute([$idPedido, $id, $qtd, $precos[$id]]);
        $totalAdicionado += (float)$precos[$id] * $qtd;
    }
    $pdo->prepare("UPDATE pedidos SET total = total + ? WHERE id = ?")->execute([$totalAdicionado, $idPedido]);
    $pdo->prepare("UPDATE mesas SET status = 'ocupada' WHERE id = ?")->execute([$mesa['id']]);
    $pdo->commit();
    echo json_encode(['sucesso' => true, 'id_pedido' => (int)$idPedido], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
