-- Execute uma vez em instalações que já possuem a tabela pedidos.
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(20) NOT NULL UNIQUE,
    cep VARCHAR(10) NULL,
    logradouro VARCHAR(150) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NOT NULL,
    cidade VARCHAR(100) NULL,
    ponto_referencia VARCHAR(150) NULL,
    observacoes TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE pedidos
    ADD COLUMN tipo_pedido ENUM('mesa', 'totem', 'delivery') NOT NULL DEFAULT 'mesa',
    ADD COLUMN id_cliente INT NULL,
    ADD COLUMN taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN status_delivery ENUM('em_preparo', 'saiu_entrega', 'entregue', 'cancelado') NULL,
    ADD CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id) ON DELETE SET NULL;
