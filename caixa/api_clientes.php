<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function normalizarTelefone(string $telefone): string { return preg_replace('/\D+/', '', $telefone) ?? ''; }
function responder(array $dados, int $status = 200): void { http_response_code($status); echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function clienteSelect(): string { return 'id,nome,telefone,cep,logradouro,numero,complemento,bairro,cidade,ponto_referencia,observacoes,criado_em'; }

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $busca = trim((string)($_GET['busca'] ?? ''));
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $limite = min(100, max(1, (int)($_GET['limite'] ?? 10)));
        $where = ''; $parametros = [];
        if ($busca !== '') {
            $digitos = normalizarTelefone($busca);
            $where = ' WHERE nome LIKE ? OR telefone LIKE ?';
            $parametros = ['%' . $busca . '%', '%' . $digitos . '%'];
        }
        if (isset($_GET['busca'])) {
            $stmt = $pdo->prepare('SELECT ' . clienteSelect() . ' FROM clientes' . $where . ' ORDER BY nome LIMIT 10');
            $stmt->execute($parametros);
            responder(['sucesso'=>true,'clientes'=>$stmt->fetchAll()]);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes' . $where); $stmt->execute($parametros); $total = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT ' . clienteSelect() . ' FROM clientes' . $where . ' ORDER BY nome LIMIT ' . $limite . ' OFFSET ' . (($pagina - 1) * $limite));
        $stmt->execute($parametros);
        responder(['sucesso'=>true,'clientes'=>$stmt->fetchAll(),'total'=>$total,'pagina'=>$pagina,'paginas'=>max(1,(int)ceil($total/$limite))]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['sucesso'=>false,'erro'=>'Método não permitido.'],405);
    $dados = json_decode(file_get_contents('php://input'), true);
    if (!is_array($dados)) responder(['sucesso'=>false,'erro'=>'Dados inválidos.'],400);
    if (($dados['acao'] ?? '') === 'excluir') {
        $id = filter_var($dados['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id) responder(['sucesso'=>false,'erro'=>'Cliente inválido.'],400);
        $stmt=$pdo->prepare('DELETE FROM clientes WHERE id=?'); $stmt->execute([$id]);
        responder(['sucesso'=>true]);
    }
    $campos = ['nome'=>120,'telefone'=>20,'cep'=>10,'logradouro'=>150,'numero'=>20,'complemento'=>100,'bairro'=>100,'cidade'=>100,'ponto_referencia'=>150,'observacoes'=>null];
    $cliente=[];
    foreach ($campos as $campo=>$max) {
        $valor=trim((string)($dados[$campo] ?? ''));
        if ($max !== null && mb_strlen($valor) > $max) responder(['sucesso'=>false,'erro'=>"O campo {$campo} excede o limite de {$max} caracteres."],400);
        $cliente[$campo] = $valor === '' ? null : $valor;
    }
    $telefone=normalizarTelefone((string)($cliente['telefone'] ?? ''));
    if ($telefone === '' || strlen($telefone) < 10 || strlen($telefone) > 13) responder(['sucesso'=>false,'erro'=>'Informe um telefone válido com DDD.'],400);
    $cliente['telefone']=$telefone;
    foreach (['nome','logradouro','numero','bairro'] as $obrigatorio) if (empty($cliente[$obrigatorio])) responder(['sucesso'=>false,'erro'=>"O campo {$obrigatorio} é obrigatório."],400);
    $id=filter_var($dados['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
    $stmt=$pdo->prepare('SELECT id FROM clientes WHERE telefone=? AND id <> COALESCE(?,0)'); $stmt->execute([$telefone,$id]); $duplicado=$stmt->fetchColumn();
    if ($duplicado && empty($dados['confirmar_atualizacao'])) responder(['sucesso'=>false,'duplicado'=>true,'id'=>(int)$duplicado,'erro'=>'Este telefone já está cadastrado. Confirme se deseja atualizar o cadastro existente.'],409);
    if ($duplicado) $id=(int)$duplicado;
    $values=array_values($cliente);
    if ($id) {
        $stmt=$pdo->prepare('UPDATE clientes SET nome=?,telefone=?,cep=?,logradouro=?,numero=?,complemento=?,bairro=?,cidade=?,ponto_referencia=?,observacoes=? WHERE id=?');
        $stmt->execute([...$values,$id]);
    } else {
        $stmt=$pdo->prepare('INSERT INTO clientes (nome,telefone,cep,logradouro,numero,complemento,bairro,cidade,ponto_referencia,observacoes) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute($values); $id=(int)$pdo->lastInsertId();
    }
    $stmt=$pdo->prepare('SELECT ' . clienteSelect() . ' FROM clientes WHERE id=?'); $stmt->execute([$id]);
    responder(['sucesso'=>true,'id'=>$id,'cliente'=>$stmt->fetch()]);
} catch (Throwable $e) {
    $duplicidade = $e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062;
    responder(['sucesso'=>false,'erro'=>$duplicidade?'Este telefone já está cadastrado.':$e->getMessage()],$duplicidade?409:500);
}
