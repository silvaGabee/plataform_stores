-- Plataforma Multi-Loja - Schema do Banco de Dados
-- Execute este arquivo no MySQL para criar todas as tabelas

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS plataform_stores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plataform_stores;

-- 1) Lojas
CREATE TABLE stores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    banner_path VARCHAR(512) DEFAULT NULL,
    store_icon_path VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- 2) Configurações PIX por loja (1:1)
CREATE TABLE store_pix_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL UNIQUE,
    pix_key VARCHAR(255) DEFAULT NULL,
    pix_key_type ENUM('cpf','cnpj','email','telefone','aleatoria') DEFAULT 'aleatoria',
    merchant_name VARCHAR(255) DEFAULT NULL,
    merchant_city VARCHAR(100) DEFAULT NULL,
    provider VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store (store_id)
) ENGINE=InnoDB;

-- 3) Usuários (clientes e funcionários por loja)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('cliente','funcionario','gerente') NOT NULL DEFAULT 'cliente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email_store (email, store_id),
    INDEX idx_store (store_id),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- 4) Cargos (hierarquia por loja)
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    parent_role_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_role_id) REFERENCES roles(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_parent (parent_role_id)
) ENGINE=InnoDB;

-- 5) Funcionário <-> Cargo
CREATE TABLE employee_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB;

-- 6) Produtos
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    cost_price DECIMAL(12,2) DEFAULT 0.00,
    sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store (store_id)
) ENGINE=InnoDB;

-- 6.1) Fotos do produto (várias por produto)
CREATE TABLE product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- 7) Movimentações de estoque
CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    type ENUM('entrada','saida','ajuste','devolucao') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_product (product_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- 8) Pedidos
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    order_type ENUM('online','pdv') NOT NULL,
    status ENUM('pendente','pago','cancelado','enviado') NOT NULL DEFAULT 'pendente',
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_store (store_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- 9) Itens do pedido
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- 10) Pagamentos
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NOT NULL,
    method ENUM('dinheiro','cartao','pix') NOT NULL,
    status ENUM('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
    amount DECIMAL(12,2) NOT NULL,
    pix_qr_code TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_store (store_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- 11) Turnos de caixa
CREATE TABLE cash_registers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    opened_by INT UNSIGNED NOT NULL,
    initial_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    final_amount DECIMAL(12,2) DEFAULT NULL,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_store (store_id),
    INDEX idx_opened (opened_at)
) ENGINE=InnoDB;

-- 12) Movimentações de caixa
CREATE TABLE cash_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    type ENUM('entrada','saida') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_cash_register (cash_register_id),
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- Metas: meta da loja por período (ex.: mês)
CREATE TABLE IF NOT EXISTS store_goals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    goal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_period (store_id, period),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_period (store_id, period)
) ENGINE=InnoDB;

-- Metas por funcionário (pode ser preenchido pela divisão da meta da loja)
CREATE TABLE IF NOT EXISTS employee_goals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    goal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_user_period (store_id, user_id, period),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_store_period (store_id, period)
) ENGINE=InnoDB;

-- Configuração do dashboard personalizado (blocos que o gerente escolhe)
CREATE TABLE IF NOT EXISTS store_dashboard_config (
    store_id INT UNSIGNED NOT NULL PRIMARY KEY,
    widgets_config TEXT DEFAULT NULL COMMENT 'JSON: [{id, type, title}, ...]',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
