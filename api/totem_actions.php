<?php
// api/totem_actions.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$acao = $_REQUEST['acao'] ?? '';

try {
    // FINALIZAR PEDIDO DO TOTEM (pagamento feito no próprio totem)
    if ($acao === 'finalizar_pedido' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!is_array($dados)) throw new Exception('Dados do pedido inválidos.');

        $tipo_consumo = $dados['tipo_consumo'] ?? '';
        if (!in_array($tipo_consumo, ['local', 'viagem'], true)) throw new Exception('Escolha consumo local ou para viagem.');

        $formas_validas = ['Dinheiro', 'Cartão Crédito', 'Cartão Débito', 'PIX'];
        $forma_pagamento = $dados['forma_pagamento'] ?? '';
        if (!in_array($forma_pagamento, $formas_validas, true)) throw new Exception('Forma de pagamento inválida.');

        $itens = $dados['itens'] ?? [];
        if (!is_array($itens) || count($itens) === 0) throw new Exception('O carrinho está vazio.');
        if (count($itens) > 50) throw new Exception('Pedido com itens demais.');

        // Valida produtos e usa sempre o preço do banco
        $ids = [];
        foreach ($itens as $item) {
            $id = filter_var($item['id_produto'] ?? 0, FILTER_VALIDATE_INT);
            $qtd = filter_var($item['quantidade'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if (!$id || !$qtd) throw new Exception('Item inválido no carrinho.');
            $ids[] = $id;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtProd = $pdo->prepare("SELECT id, preco FROM produtos WHERE ativo = 1 AND id IN ($placeholders)");
        $stmtProd->execute($ids);
        $precos = array_column($stmtProd->fetchAll(), 'preco', 'id');

        $total = 0;
        foreach ($itens as $item) {
            if (!isset($precos[(int) $item['id_produto']])) throw new Exception('Um dos produtos não está mais disponível.');
            $total += $precos[(int) $item['id_produto']] * (int) $item['quantidade'];
        }

        $pdo->beginTransaction();

        // Dinheiro é pago no caixa: a conta fica aberta até o caixa confirmar o recebimento
        $pagamento_pendente = $forma_pagamento === 'Dinheiro';
        $stmtPedido = $pdo->prepare("
            INSERT INTO pedidos (origem, tipo_consumo, id_mesa, forma_pagamento, status_conta, total, data_fechamento)
            VALUES ('totem', ?, NULL, ?, ?, ?, " . ($pagamento_pendente ? 'NULL' : 'NOW()') . ")
        ");
        $stmtPedido->execute([$tipo_consumo, $forma_pagamento, $pagamento_pendente ? 'aberta' : 'fechada', $total]);
        $id_pedido = (int) $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, observacao)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($itens as $item) {
            $obs = trim((string) ($item['observacao'] ?? ''));
            $obs = function_exists('mb_substr') ? mb_substr($obs, 0, 255) : substr($obs, 0, 255);
            $stmtItem->execute([
                $id_pedido,
                (int) $item['id_produto'],
                (int) $item['quantidade'],
                $precos[(int) $item['id_produto']],
                $obs !== '' ? $obs : null,
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'sucesso' => true,
            'id_pedido' => $id_pedido,
            'pagamento_pendente' => $pagamento_pendente,
            'senha' => str_pad((string) ($id_pedido % 1000), 3, '0', STR_PAD_LEFT),
            'total_formatado' => number_format($total, 2, ',', '.'),
        ]);
        exit;
    }

    throw new Exception('Ação não reconhecida.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => $e instanceof Exception && !($e instanceof PDOException) ? $e->getMessage() : 'Erro ao registrar o pedido.']);
}
