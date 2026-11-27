<?php
// api/caixa_actions.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$acao = $_REQUEST['acao'] ?? '';

try {
    // 1. BUSCAR DETALHES DE UMA MESA (Itens na cozinha, itens consumidos e totais)
    if ($acao === 'detalhes_mesa') {
        $id_mesa = filter_input(INPUT_GET, 'id_mesa', FILTER_VALIDATE_INT);
        if (!$id_mesa) throw new Exception('ID da mesa inválido.');

        // Dados da mesa
        $stmtMesa = $pdo->prepare("SELECT * FROM mesas WHERE id = ?");
        $stmtMesa->execute([$id_mesa]);
        $mesa = $stmtMesa->fetch();

        // Busca pedido aberto da mesa
        $stmtPedido = $pdo->prepare("SELECT * FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' LIMIT 1");
        $stmtPedido->execute([$id_mesa]);
        $pedido = $stmtPedido->fetch();

        $itens_cozinha = [];
        $itens_entregues = [];
        $total_consumido = 0;

        if ($pedido) {
            // Calcula minutos de espera diretamente no MySQL (TIMESTAMPDIFF)
            $sqlItens = "
                SELECT 
                    ip.id,
                    ip.quantidade,
                    ip.preco_unitario,
                    ip.observacao,
                    ip.status_item,
                    DATE_FORMAT(ip.timestamp_pedido, '%H:%i') AS hora_pedido,
                    DATE_FORMAT(ip.timestamp_entrega, '%H:%i') AS hora_entrega,
                    TIMESTAMPDIFF(MINUTE, ip.timestamp_pedido, NOW()) AS minutos_espera,
                    p.nome AS produto_nome
                FROM itens_pedido ip
                JOIN produtos p ON ip.id_produto = p.id
                WHERE ip.id_pedido = ?
                ORDER BY ip.timestamp_pedido ASC
            ";
            $stmtItens = $pdo->prepare($sqlItens);
            $stmtItens->execute([$pedido['id']]);
            $todos_itens = $stmtItens->fetchAll();

            foreach ($todos_itens as $item) {
                $subtotal = $item['quantidade'] * $item['preco_unitario'];
                $item['subtotal'] = number_format($subtotal, 2, ',', '.');
                $total_consumido += $subtotal;

                if ($item['status_item'] === 'na_cozinha') {
                    $itens_cozinha[] = $item;
                } else {
                    $itens_entregues[] = $item;
                }
            }
        }

        echo json_encode([
            'sucesso' => true,
            'mesa' => $mesa,
            'pedido' => $pedido,
            'itens_cozinha' => $itens_cozinha,
            'itens_entregues' => $itens_entregues,
            'total_formatado' => number_format($total_consumido, 2, ',', '.')
        ]);
        exit;
    }

    // 2. MARCAR ITEM COMO ENTREGUE NA MESA
    if ($acao === 'marcar_entregue' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $id_item = filter_var($dados['id_item'] ?? 0, FILTER_VALIDATE_INT);

        if (!$id_item) throw new Exception('Item inválido.');

        $stmt = $pdo->prepare("
            UPDATE itens_pedido ip
            JOIN pedidos p ON p.id = ip.id_pedido
            SET ip.status_item = 'entregue', ip.timestamp_entrega = NOW()
            WHERE ip.id = ? AND ip.status_item = 'na_cozinha'
              AND (
                    (p.id_mesa IS NOT NULL AND p.status_conta = 'aberta')
                 OR (p.origem = 'totem' AND p.status_conta = 'fechada')
              )
        ");
        $stmt->execute([$id_item]);
        if ($stmt->rowCount() === 0) throw new Exception('Item não encontrado, já entregue ou com pagamento pendente.');

        echo json_encode(['sucesso' => true, 'mensagem' => 'Item marcado como entregue!']);
        exit;
    }

    // 3. ABRIR MESA MANUALMENTE PELO CAIXA
    if ($acao === 'abrir_mesa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $id_mesa = filter_var($dados['id_mesa'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id_mesa) throw new Exception('ID da mesa inválido.');

        $pdo->beginTransaction();
        $stmtMesa = $pdo->prepare("SELECT status FROM mesas WHERE id = ? FOR UPDATE");
        $stmtMesa->execute([$id_mesa]);
        $statusMesa = $stmtMesa->fetchColumn();
        if ($statusMesa === false) throw new Exception('Mesa não encontrada.');
        if ($statusMesa !== 'livre') throw new Exception('A mesa já está aberta.');

        $pdo->prepare("UPDATE mesas SET status = 'ocupada' WHERE id = ?")->execute([$id_mesa]);
        $pdo->prepare("INSERT INTO pedidos (origem, tipo_consumo, id_mesa, status_conta) VALUES ('web', 'local', ?, 'aberta')")->execute([$id_mesa]);
        $pdo->commit();

        echo json_encode(['sucesso' => true]);
        exit;
    }

    // 4. FECHAR CONTA E LIBERAR MESA
    if ($acao === 'fechar_conta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $id_mesa = filter_var($dados['id_mesa'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id_mesa) throw new Exception('ID da mesa inválido.');

        $formas_validas = ['Dinheiro', 'Cartão Crédito', 'Cartão Débito', 'PIX'];
        $forma_pagamento = $dados['forma_pagamento'] ?? 'Dinheiro';
        if (!in_array($forma_pagamento, $formas_validas, true)) throw new Exception('Forma de pagamento inválida.');

        $pdo->beginTransaction();

        // Trava a mesa primeiro, na mesma ordem usada pelo tablet, para impedir
        // novos lançamentos enquanto o caixa recebe a conta.
        $stmtMesa = $pdo->prepare('SELECT id FROM mesas WHERE id = ? FOR UPDATE');
        $stmtMesa->execute([$id_mesa]);
        if (!$stmtMesa->fetchColumn()) throw new Exception('Mesa não encontrada.');

        // Calcula o total real dos itens do pedido aberto
        $stmtPed = $pdo->prepare("SELECT id FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' LIMIT 1 FOR UPDATE");
        $stmtPed->execute([$id_mesa]);
        $pedido = $stmtPed->fetch();

        if (!$pedido) throw new Exception('A mesa não possui uma conta aberta.');
        $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(quantidade * preco_unitario), 0) AS total FROM itens_pedido WHERE id_pedido = ?");
        $stmtTotal->execute([$pedido['id']]);
        $total = $stmtTotal->fetchColumn();

            $stmtFecha = $pdo->prepare("
                UPDATE pedidos 
                SET status_conta = 'fechada', total = ?, forma_pagamento = ?, data_fechamento = NOW() 
                WHERE id = ?
            ");
        $stmtFecha->execute([$total, $forma_pagamento, $pedido['id']]);

        // Libera a mesa para o próximo cliente
        $pdo->prepare("UPDATE mesas SET status = 'livre' WHERE id = ?")->execute([$id_mesa]);

        $pdo->commit();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Conta fechada e mesa liberada!']);
        exit;
    }

    // 5. PEDIDOS DO TOTEM: pendentes de pagamento ou com itens na cozinha
    if ($acao === 'pedidos_totem') {
        $pedidos = $pdo->query("
            SELECT p.id, p.tipo_consumo, p.forma_pagamento, p.status_conta, p.total,
                   DATE_FORMAT(p.data_abertura, '%H:%i') AS hora_pedido
            FROM pedidos p
            WHERE p.origem = 'totem'
              AND (p.status_conta = 'aberta'
                   OR EXISTS (SELECT 1 FROM itens_pedido ip WHERE ip.id_pedido = p.id AND ip.status_item = 'na_cozinha'))
            ORDER BY p.data_abertura ASC
        ")->fetchAll();

        $itensPorPedido = [];
        if ($pedidos) {
            $ids = array_column($pedidos, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtItens = $pdo->prepare("
                SELECT ip.id, ip.id_pedido, ip.quantidade, ip.observacao, ip.status_item,
                       TIMESTAMPDIFF(MINUTE, ip.timestamp_pedido, NOW()) AS minutos_espera,
                       pr.nome AS produto_nome
                FROM itens_pedido ip
                JOIN produtos pr ON pr.id = ip.id_produto
                WHERE ip.id_pedido IN ($placeholders)
                ORDER BY ip.id ASC
            ");
            $stmtItens->execute($ids);
            foreach ($stmtItens->fetchAll() as $item) {
                $itensPorPedido[$item['id_pedido']][] = $item;
            }
        }

        foreach ($pedidos as &$pedido) {
            $pedido['senha'] = str_pad((string) ($pedido['id'] % 1000), 3, '0', STR_PAD_LEFT);
            $pedido['total_formatado'] = number_format((float) $pedido['total'], 2, ',', '.');
            $pedido['itens'] = $itensPorPedido[$pedido['id']] ?? [];
        }
        unset($pedido);

        echo json_encode(['sucesso' => true, 'pedidos' => $pedidos]);
        exit;
    }

    // 6. CONFIRMAR PAGAMENTO DE PEDIDO DO TOTEM (pago no caixa)
    if ($acao === 'confirmar_pagamento_totem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $id_pedido = filter_var($dados['id_pedido'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id_pedido) throw new Exception('Pedido inválido.');

        $pdo->beginTransaction();
        $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(quantidade * preco_unitario), 0) FROM itens_pedido WHERE id_pedido = ?");
        $stmtTotal->execute([$id_pedido]);
        $total = $stmtTotal->fetchColumn();

        $stmt = $pdo->prepare("
            UPDATE pedidos SET status_conta = 'fechada', total = ?, data_fechamento = NOW()
            WHERE id = ? AND origem = 'totem' AND status_conta = 'aberta'
        ");
        $stmt->execute([$total, $id_pedido]);
        if ($stmt->rowCount() === 0) throw new Exception('Pedido não encontrado ou já pago.');
        $pdo->commit();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Pagamento confirmado! O pedido foi liberado para entrega.']);
        exit;
    }

    // 7. CANCELAR PEDIDO DO TOTEM NÃO PAGO (cliente desistiu)
    if ($acao === 'cancelar_pedido_totem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = json_decode(file_get_contents('php://input'), true);
        $id_pedido = filter_var($dados['id_pedido'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id_pedido) throw new Exception('Pedido inválido.');

        // itens_pedido é removido junto (ON DELETE CASCADE)
        $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ? AND origem = 'totem' AND status_conta = 'aberta'");
        $stmt->execute([$id_pedido]);
        if ($stmt->rowCount() === 0) throw new Exception('Só é possível cancelar pedidos do totem ainda não pagos.');

        echo json_encode(['sucesso' => true, 'mensagem' => 'Pedido cancelado.']);
        exit;
    }

    throw new Exception('Ação não reconhecida.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
