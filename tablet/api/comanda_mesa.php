<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
try {
    $numero = filter_input(INPUT_GET, 'mesa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$numero) throw new RuntimeException('Mesa inválida.');
    $stmt = $pdo->prepare('SELECT id, numero, status FROM mesas WHERE numero = ?');
    $stmt->execute([$numero]); $mesa = $stmt->fetch();
    if (!$mesa) throw new RuntimeException('Mesa não encontrada.');
    $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$mesa['id']]); $pedidoId = $stmt->fetchColumn();
    $itens = [];
    $total = 0.0;
    if ($pedidoId) {
        $stmt = $pdo->prepare("SELECT ip.id, ip.quantidade, ip.preco_unitario, ip.status_item,
            DATE_FORMAT(ip.timestamp_pedido, '%H:%i') AS hora_pedido,
            pr.nome AS produto_nome
            FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.id_produto
            WHERE ip.id_pedido = ? ORDER BY ip.timestamp_pedido ASC, ip.id ASC");
        $stmt->execute([$pedidoId]);
        foreach ($stmt->fetchAll() as $item) {
            $item['subtotal'] = (float)$item['quantidade'] * (float)$item['preco_unitario'];
            $total += $item['subtotal'];
            $itens[] = $item;
        }
    }
    echo json_encode(['sucesso' => true, 'mesa' => $mesa, 'pedido_id' => $pedidoId ? (int)$pedidoId : null,
        'itens_cozinha' => array_values(array_filter($itens, fn($i) => $i['status_item'] === 'na_cozinha')),
        'itens_entregues' => array_values(array_filter($itens, fn($i) => $i['status_item'] === 'entregue')),
        'total' => $total], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
