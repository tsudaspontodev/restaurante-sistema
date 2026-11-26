<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
try {
    if (($_GET['acao'] ?? '') === 'mapa') {
        $mesas = $pdo->query("SELECT m.id,m.numero,m.status,
            COALESCE((SELECT SUM(ip.quantidade*ip.preco_unitario) FROM pedidos p JOIN itens_pedido ip ON ip.id_pedido=p.id WHERE p.id_mesa=m.id AND p.status_conta='aberta'),0) valor,
            (SELECT COUNT(*) FROM pedidos p JOIN itens_pedido ip ON ip.id_pedido=p.id WHERE p.id_mesa=m.id AND p.status_conta='aberta' AND ip.status_item='na_cozinha') qtd_cozinha
            FROM mesas m ORDER BY m.numero")->fetchAll();
        echo json_encode(['sucesso'=>true,'mesas'=>$mesas], JSON_UNESCAPED_UNICODE); exit;
    }
    $idMesa = filter_input(INPUT_GET, 'id_mesa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idMesa) throw new RuntimeException('ID da mesa inválido.');
    $stmt = $pdo->prepare('SELECT id, numero, status FROM mesas WHERE id = ?');
    $stmt->execute([$idMesa]); $mesa = $stmt->fetch();
    if (!$mesa) throw new RuntimeException('Mesa não encontrada.');
    $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$idMesa]); $pedidoId = $stmt->fetchColumn();
    $itens = [];
    $total = 0.0;
    if ($pedidoId) {
        $stmt = $pdo->prepare("SELECT ip.id, ip.quantidade, ip.preco_unitario, ip.observacao, ip.status_item,
            DATE_FORMAT(ip.timestamp_pedido, '%H:%i') hora_pedido, DATE_FORMAT(ip.timestamp_entrega, '%H:%i') hora_entrega,
            TIMESTAMPDIFF(MINUTE, ip.timestamp_pedido, NOW()) minutos_espera, pr.nome produto_nome
            FROM itens_pedido ip JOIN produtos pr ON pr.id = ip.id_produto WHERE ip.id_pedido = ? ORDER BY ip.timestamp_pedido, ip.id");
        $stmt->execute([$pedidoId]);
        foreach ($stmt->fetchAll() as $item) {
            $item['subtotal'] = (float)$item['quantidade'] * (float)$item['preco_unitario'];
            $total += $item['subtotal']; $itens[] = $item;
        }
    }
    echo json_encode(['sucesso'=>true,'mesa'=>$mesa,'pedido_id'=>$pedidoId ? (int)$pedidoId : null,'itens'=>$itens,
        'itens_cozinha'=>array_values(array_filter($itens,fn($i)=>$i['status_item']==='na_cozinha')),
        'itens_entregues'=>array_values(array_filter($itens,fn($i)=>$i['status_item']==='entregue')),
        'total'=>(float)$total,'total_formatado'=>number_format($total,2,',','.')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode(['sucesso'=>false,'erro'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
