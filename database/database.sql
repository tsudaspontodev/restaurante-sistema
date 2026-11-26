CREATE DATABASE IF NOT EXISTS restaurante_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurante_db;

-- 1. Tabela de Mesas
CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NOT NULL UNIQUE,
    status ENUM('livre', 'ocupada', 'fechamento') DEFAULT 'livre'
);

-- 2. Tabela de Categorias
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    ordem INT DEFAULT 0
);

-- 3. Tabela de Produtos
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    imagem_url VARCHAR(255) DEFAULT 'https://placehold.co/400x300?text=Produto',
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id) ON DELETE CASCADE
);

-- 4. Tabela de Pedidos (Comandas/Contas)
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origem ENUM('totem', 'tablet', 'web') NOT NULL,
    tipo_consumo ENUM('local', 'viagem') DEFAULT 'local',
    id_mesa INT NULL,
    forma_pagamento VARCHAR(50) DEFAULT 'Dinheiro',
    status_conta ENUM('aberta', 'fechada') DEFAULT 'aberta',
    total DECIMAL(10,2) DEFAULT 0.00,
    data_abertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_fechamento DATETIME NULL,
    FOREIGN KEY (id_mesa) REFERENCES mesas(id) ON DELETE SET NULL
);

-- 5. Tabela de Itens do Pedido (Controle de Cozinha x Entregue)
CREATE TABLE itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL,
    observacao TEXT NULL,
    status_item ENUM('na_cozinha', 'entregue') DEFAULT 'na_cozinha',
    timestamp_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    timestamp_entrega DATETIME NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE RESTRICT
);

-- 6. Tabela de Sugestões e Críticas (Tablet)
CREATE TABLE feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mesa INT NULL,
    avaliacao TINYINT NOT NULL CHECK (avaliacao BETWEEN 1 AND 5),
    sugestao TEXT,
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mesa) REFERENCES mesas(id) ON DELETE SET NULL
);

-- ==========================================================
-- DADOS INICIAIS PARA TESTE IMEDIATO
-- ==========================================================

INSERT INTO mesas (numero, status) VALUES 
(1, 'livre'), (2, 'ocupada'), (3, 'livre'), (4, 'fechamento'),
(5, 'ocupada'), (6, 'livre'), (7, 'livre'), (8, 'livre');

INSERT INTO categorias (nome, ordem) VALUES 
('Hambúrgueres Artesanais', 1),
('Porções & Entradas', 2),
('Bebidas', 3),
('Sobremesas', 4);

INSERT INTO produtos (id_categoria, nome, descricao, preco, imagem_url) VALUES
(1, 'Classic Smash Burger', 'Pão brioche, burger 160g, queijo cheddar, alface, tomate e molho especial.', 32.90, 'https://placehold.co/400x300/222/fff?text=Classic+Burger'),
(1, 'Bacon BBQ Duplo', 'Dois burgers 140g, bacon crocante, cebola caramelizada e molho barbecue.', 41.50, 'https://placehold.co/400x300/222/fff?text=Bacon+BBQ'),
(2, 'Batata Rústica com Alecrim', 'Porção generosa de batatas rústicas com maionese verde da casa.', 24.00, 'https://placehold.co/400x300/222/fff?text=Batata+Rustica'),
(3, 'Suco Natural de Laranja 500ml', 'Espremido na hora, sem adição de açúcar.', 12.00, 'https://placehold.co/400x300/222/fff?text=Suco+Laranja'),
(3, 'Refrigerante Lata 350ml', 'Coca-Cola, Guaraná Antarctica ou Sprite.', 7.50, 'https://placehold.co/400x300/222/fff?text=Refrigerante'),
(4, 'Brownie com Sorvete', 'Brownie de chocolate meio amargo quente com bola de sorvete de creme.', 22.00, 'https://placehold.co/400x300/222/fff?text=Brownie');

-- Simulando pedidos abertos nas Mesas 2, 4 e 5
INSERT INTO pedidos (id, origem, tipo_consumo, id_mesa, status_conta, total, data_abertura) VALUES
(1, 'tablet', 'local', 2, 'aberta', 77.80, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
(2, 'tablet', 'local', 4, 'aberta', 53.50, DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
(3, 'tablet', 'local', 5, 'aberta', 32.90, DATE_SUB(NOW(), INTERVAL 8 MINUTE));

-- Itens da Mesa 2 (1 já entregue, 1 aguardando na cozinha há 14 min)
INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, observacao, status_item, timestamp_pedido, timestamp_entrega) VALUES
(1, 3, 1, 24.00, 'Sem sal extra', 'entregue', DATE_SUB(NOW(), INTERVAL 35 MINUTE), DATE_SUB(NOW(), INTERVAL 22 MINUTE)),
(1, 1, 1, 32.90, 'Ponto da carne: bem passado', 'na_cozinha', DATE_SUB(NOW(), INTERVAL 14 MINUTE), NULL),
(1, 4, 1, 12.00, 'Com gelo e limão', 'na_cozinha', DATE_SUB(NOW(), INTERVAL 14 MINUTE), NULL);

-- Itens da Mesa 4 (Aguardando fechamento - todos entregues)
INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, observacao, status_item, timestamp_pedido, timestamp_entrega) VALUES
(2, 2, 1, 41.50, '', 'entregue', DATE_SUB(NOW(), INTERVAL 48 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 4, 1, 12.00, '', 'entregue', DATE_SUB(NOW(), INTERVAL 48 MINUTE), DATE_SUB(NOW(), INTERVAL 42 MINUTE));

-- Itens da Mesa 5 (Pedido recente na cozinha há 8 min)
INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario, observacao, status_item, timestamp_pedido, timestamp_entrega) VALUES
(3, 1, 1, 32.90, 'Tirar cebola e tomate', 'na_cozinha', DATE_SUB(NOW(), INTERVAL 8 MINUTE), NULL);