<?php

declare(strict_types=1);

// Fuso horário padrão da aplicação
date_default_timezone_set('America/Sao_Paulo');

// Lê variáveis de ambiente se existirem; caso contrário, usa padrões locais
$host = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
$dbPort = $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: '3306');
$dbname = $_ENV['DB_NAME'] ?? (getenv('DB_NAME') ?: 'restaurante_db');
$username = $_ENV['DB_USER'] ?? (getenv('DB_USER') ?: 'root');
$password = $_ENV['DB_PASS'] ?? (getenv('DB_PASS') ?: '');
$dbCharset = 'utf8mb4';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $host,
    $dbPort,
    $dbname,
    $dbCharset
);

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'",
];

try {
    $pdo = new PDO($dsn, $username, $password, $opcoes);
} catch (PDOException $e) {
    // Registra o erro detalhado no log do servidor sem expor credenciais na tela
    error_log('Erro de conexão com o banco de dados: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Não foi possível conectar ao banco de dados. Verifique as configurações do servidor.');
}
