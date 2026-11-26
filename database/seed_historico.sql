-- Opcional: contas fechadas nos últimos dias para popular o Dashboard BI
-- Rode depois de database.sql
USE restaurante_db;

INSERT INTO pedidos (id, origem, tipo_consumo, id_mesa, forma_pagamento, status_conta, data_abertura, data_fechamento) VALUES
(101, 'tablet', 'local', 1, 'PIX',            'fechada', NOW() - INTERVAL 6 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 6 DAY - INTERVAL 1 HOUR),
(102, 'tablet', 'local', 3, 'Cartão Crédito', 'fechada', NOW() - INTERVAL 4 DAY - INTERVAL 3 HOUR, NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR),
(103, 'totem',  'viagem', NULL, 'Dinheiro',   'fechada', NOW() - INTERVAL 2 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR),
(104, 'tablet', 'local', 6, 'Cartão Débito',  'fechada', NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 1 DAY - INTERVAL 1 HOUR);

INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, status_item, timestamp_pedido, timestamp_entrega)
SELECT p.id, x.id_produto, x.qtd, pr.preco, 'entregue', p.data_abertura, p.data_abertura + INTERVAL x.minutos MINUTE
FROM pedidos p
JOIN (
    SELECT 101 AS id_pedido, 1 AS id_produto, 2 AS qtd, 18 AS minutos UNION ALL
    SELECT 101, 5, 2, 3 UNION ALL
    SELECT 102, 2, 3, 22 UNION ALL
    SELECT 102, 3, 1, 15 UNION ALL
    SELECT 102, 4, 3, 5 UNION ALL
    SELECT 103, 1, 1, 14 UNION ALL
    SELECT 103, 5, 1, 2 UNION ALL
    SELECT 104, 2, 2, 25 UNION ALL
    SELECT 104, 6, 2, 12 UNION ALL
    SELECT 104, 5, 2, 2
) x ON x.id_pedido = p.id
JOIN produtos pr ON pr.id = x.id_produto;

UPDATE pedidos p
SET total = (SELECT SUM(quantidade * preco_unitario) FROM itens_pedido WHERE id_pedido = p.id)
WHERE p.id BETWEEN 101 AND 104;
