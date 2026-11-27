<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $sql = "SELECT m.id, m.numero, m.status, p.id AS id_pedido,
                   (SELECT COUNT(*) FROM itens_pedido ip WHERE ip.id_pedido = p.id AND ip.status_item = 'na_cozinha') AS qtd_cozinha,
                   (SELECT MAX(TIMESTAMPDIFF(MINUTE, ip.timestamp_pedido, NOW())) FROM itens_pedido ip WHERE ip.id_pedido = p.id AND ip.status_item = 'na_cozinha') AS max_espera,
                   (SELECT COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) FROM itens_pedido ip WHERE ip.id_pedido = p.id) AS valor_parcial
            FROM mesas m
            LEFT JOIN pedidos p ON p.id = (
                SELECT MAX(p2.id) FROM pedidos p2
                WHERE p2.id_mesa = m.id AND p2.status_conta = 'aberta'
            )
            ORDER BY m.numero ASC";
    $mesas = $pdo->query($sql)->fetchAll();
    echo json_encode(['sucesso' => true, 'mesas' => $mesas], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível atualizar o mapa de mesas.'], JSON_UNESCAPED_UNICODE);
}
