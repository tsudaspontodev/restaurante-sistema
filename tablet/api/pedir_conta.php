<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método não permitido.');
    $dados = json_decode(file_get_contents('php://input'), true);
    $numero = filter_var($dados['mesa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$numero) throw new RuntimeException('Mesa inválida.');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, status FROM mesas WHERE numero = ? FOR UPDATE');
    $stmt->execute([$numero]); $mesa = $stmt->fetch();
    if (!$mesa) throw new RuntimeException('Mesa não encontrada.');
    if ($mesa['status'] === 'livre') throw new RuntimeException('A mesa não possui uma conta aberta.');
    $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id_mesa = ? AND status_conta = 'aberta' LIMIT 1 FOR UPDATE");
    $stmt->execute([$mesa['id']]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('A mesa não possui uma conta aberta.');
    $pdo->prepare("UPDATE mesas SET status = 'fechamento' WHERE id = ?")->execute([$mesa['id']]);
    $pdo->commit();
    echo json_encode(['sucesso' => true, 'status' => 'fechamento'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
