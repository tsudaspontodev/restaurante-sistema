<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function deliveryResponder(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['busca_cliente'])) {
            $busca = trim((string) $_GET['busca_cliente']);
            $digitos = preg_replace('/\D+/', '', $busca) ?? '';
            if ($digitos === '') {
                $stmt = $pdo->prepare("SELECT id,nome,telefone,cep,logradouro,numero,complemento,bairro,cidade,ponto_referencia,observacoes
                    FROM clientes WHERE nome LIKE ? ORDER BY nome LIMIT 10");
                $stmt->execute(['%' . $busca . '%']);
            } else {
                $stmt = $pdo->prepare("SELECT id,nome,telefone,cep,logradouro,numero,complemento,bairro,cidade,ponto_referencia,observacoes
                    FROM clientes WHERE nome LIKE ? OR telefone LIKE ? ORDER BY nome LIMIT 10");
                $stmt->execute(['%' . $busca . '%', '%' . $digitos . '%']);
            }
            deliveryResponder(['sucesso'=>true,'clientes'=>$stmt->fetchAll()]);
        }

        $pedidos = $pdo->query("SELECT p.id,p.id_cliente,p.status_delivery,p.forma_pagamento,p.total,p.taxa_entrega,
                c.nome AS cliente_nome,c.telefone,c.logradouro,c.numero,c.complemento,c.bairro,c.cidade,c.ponto_referencia
            FROM pedidos p JOIN clientes c ON c.id=p.id_cliente
            WHERE p.tipo_pedido='delivery' AND p.status_conta='aberta'
              AND p.status_delivery IN ('em_preparo','saiu_entrega')
            ORDER BY p.data_abertura ASC")->fetchAll();
        if ($pedidos) {
            $ids = array_column($pedidos, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT ip.id_pedido,ip.quantidade,ip.preco_unitario,pr.nome AS produto_nome
                FROM itens_pedido ip JOIN produtos pr ON pr.id=ip.id_produto
                WHERE ip.id_pedido IN ($marks) ORDER BY ip.id");
            $stmt->execute($ids);
            $itens = [];
            foreach ($stmt->fetchAll() as $item) $itens[$item['id_pedido']][] = $item;
            foreach ($pedidos as &$pedido) {
                $pedido['itens'] = $itens[$pedido['id']] ?? [];
                $pedido['total_formatado'] = number_format((float)$pedido['total'], 2, ',', '.');
            }
            unset($pedido);
        }
        deliveryResponder(['sucesso'=>true,'pedidos'=>$pedidos]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') deliveryResponder(['sucesso'=>false,'erro'=>'Método não permitido.'],405);
    $dados = json_decode(file_get_contents('php://input'), true);
    if (!is_array($dados)) deliveryResponder(['sucesso'=>false,'erro'=>'Dados inválidos.'],400);

    if (($dados['acao'] ?? '') === 'atualizar_status') {
        $id = filter_var($dados['id_pedido'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $status = (string)($dados['status_delivery'] ?? '');
        if (!$id || !in_array($status, ['saiu_entrega','entregue'], true)) deliveryResponder(['sucesso'=>false,'erro'=>'Pedido ou status inválido.'],400);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id,status_delivery FROM pedidos WHERE id=? AND tipo_pedido='delivery' AND status_conta='aberta' FOR UPDATE");
        $stmt->execute([$id]); $pedido = $stmt->fetch();
        if (!$pedido) throw new RuntimeException('Pedido delivery aberto não encontrado.');
        if ($status === 'saiu_entrega' && $pedido['status_delivery'] !== 'em_preparo') throw new RuntimeException('Este pedido não está em preparo.');
        if ($status === 'entregue' && $pedido['status_delivery'] !== 'saiu_entrega') throw new RuntimeException('Despache o pedido antes de confirmar a entrega.');
        if ($status === 'entregue') {
            $pdo->prepare("UPDATE pedidos SET status_delivery='entregue',status_conta='fechada',data_fechamento=NOW() WHERE id=?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE pedidos SET status_delivery='saiu_entrega' WHERE id=?")->execute([$id]);
        }
        $pdo->commit();
        deliveryResponder(['sucesso'=>true]);
    }

    if (($dados['acao'] ?? '') !== 'criar_pedido') deliveryResponder(['sucesso'=>false,'erro'=>'Ação inválida.'],400);
    $itens = $dados['itens'] ?? [];
    if (!is_array($itens) || count($itens) < 1) deliveryResponder(['sucesso'=>false,'erro'=>'Adicione ao menos um produto ao pedido.'],400);
    $taxa = filter_var($dados['taxa_entrega'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($taxa === false || $taxa < 0 || $taxa > 99999999) deliveryResponder(['sucesso'=>false,'erro'=>'Taxa de entrega inválida.'],400);
    $pagamento = (string)($dados['forma_pagamento'] ?? '');
    if (!in_array($pagamento, ['Dinheiro','Pix','Cartão na Entrega'], true)) deliveryResponder(['sucesso'=>false,'erro'=>'Forma de pagamento inválida.'],400);

    $cliente = $dados['cliente'] ?? [];
    if (!is_array($cliente)) deliveryResponder(['sucesso'=>false,'erro'=>'Dados do cliente inválidos.'],400);
    $idCliente = filter_var($dados['id_cliente'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: null;
    $nome = trim((string)($cliente['nome'] ?? ''));
    $telefone = preg_replace('/\D+/', '', (string)($cliente['telefone'] ?? '')) ?? '';
    $logradouro = trim((string)($cliente['logradouro'] ?? ''));
    $numero = trim((string)($cliente['numero'] ?? ''));
    $bairro = trim((string)($cliente['bairro'] ?? ''));
    if ($nome === '' || strlen($telefone) < 10 || strlen($telefone) > 13 || $logradouro === '' || $numero === '' || $bairro === '') {
        deliveryResponder(['sucesso'=>false,'erro'=>'Informe nome, telefone válido e endereço completo do cliente.'],400);
    }

    $pdo->beginTransaction();
    if ($idCliente) {
        $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id=? FOR UPDATE'); $stmt->execute([$idCliente]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Cliente selecionado não foi encontrado.');
        $stmt = $pdo->prepare('SELECT id FROM clientes WHERE telefone=? AND id<>?'); $stmt->execute([$telefone,$idCliente]);
        if ($stmt->fetchColumn()) throw new RuntimeException('O telefone informado pertence a outro cliente.');
        $pdo->prepare('UPDATE clientes SET nome=?,telefone=?,cep=?,logradouro=?,numero=?,complemento=?,bairro=?,cidade=?,ponto_referencia=? WHERE id=?')
            ->execute([$nome,$telefone,$cliente['cep'] ?? null,$logradouro,$numero,$cliente['complemento'] ?? null,$bairro,$cliente['cidade'] ?? null,$cliente['ponto_referencia'] ?? null,$idCliente]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM clientes WHERE telefone=?'); $stmt->execute([$telefone]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            $idCliente = (int)$existente;
            $pdo->prepare('UPDATE clientes SET nome=?,cep=?,logradouro=?,numero=?,complemento=?,bairro=?,cidade=?,ponto_referencia=? WHERE id=?')
                ->execute([$nome,$cliente['cep'] ?? null,$logradouro,$numero,$cliente['complemento'] ?? null,$bairro,$cliente['cidade'] ?? null,$cliente['ponto_referencia'] ?? null,$idCliente]);
        } else {
            $pdo->prepare('INSERT INTO clientes (nome,telefone,cep,logradouro,numero,complemento,bairro,cidade,ponto_referencia) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$nome,$telefone,$cliente['cep'] ?? null,$logradouro,$numero,$cliente['complemento'] ?? null,$bairro,$cliente['cidade'] ?? null,$cliente['ponto_referencia'] ?? null]);
            $idCliente = (int)$pdo->lastInsertId();
        }
    }

    $pdo->prepare("INSERT INTO pedidos (origem,tipo_pedido,tipo_consumo,id_mesa,id_cliente,forma_pagamento,taxa_entrega,status_conta,status_delivery,total)
        VALUES ('web','delivery','viagem',NULL,?,?,?,'aberta','em_preparo',0)")->execute([$idCliente,$pagamento,$taxa]);
    $idPedido = (int)$pdo->lastInsertId(); $subtotal = 0.0;
    $stmtProduto = $pdo->prepare('SELECT id,preco FROM produtos WHERE id=? AND ativo=1');
    $stmtItem = $pdo->prepare("INSERT INTO itens_pedido (id_pedido,id_produto,quantidade,preco_unitario,status_item,timestamp_pedido) VALUES (?,?,?,?,'na_cozinha',NOW())");
    foreach ($itens as $item) {
        $produtoId = filter_var($item['id_produto'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $quantidade = filter_var($item['quantidade'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>50]]);
        if (!$produtoId || !$quantidade) throw new RuntimeException('Produto ou quantidade inválidos no carrinho.');
        $stmtProduto->execute([$produtoId]); $produto = $stmtProduto->fetch();
        if (!$produto) throw new RuntimeException('Um dos produtos não está mais disponível.');
        $preco = (float)$produto['preco'];
        $stmtItem->execute([$idPedido,$produtoId,$quantidade,$preco]);
        $subtotal += $preco * $quantidade;
    }
    $total = $subtotal + (float)$taxa;
    $pdo->prepare('UPDATE pedidos SET total=? WHERE id=?')->execute([$total,$idPedido]);
    $pdo->commit();
    deliveryResponder(['sucesso'=>true,'id_pedido'=>$idPedido,'total'=>$total]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    deliveryResponder(['sucesso'=>false,'erro'=>$e instanceof PDOException ? 'Não foi possível salvar o pedido delivery.' : $e->getMessage()],400);
}
