-- Entrega: endereços do cliente e tipo de pedido (retirada/entrega)
-- Execute no banco plataform_stores
-- Pode ser executado mais de uma vez (idempotente).

USE plataform_stores;

-- Endereços do usuário (cliente por loja)
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    label VARCHAR(100) DEFAULT NULL COMMENT 'Ex: Casa, Trabalho',
    street VARCHAR(255) NOT NULL,
    number VARCHAR(20) NOT NULL,
    complement VARCHAR(100) DEFAULT NULL,
    neighborhood VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL,
    zipcode VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id) 
) ENGINE=InnoDB;

-- Pedidos: tipo de entrega e endereço (só adiciona se ainda não existir)
DELIMITER //
CREATE PROCEDURE add_orders_delivery_columns()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_type') = 0 THEN
    ALTER TABLE orders ADD COLUMN delivery_type ENUM('retirada','entrega') NOT NULL DEFAULT 'retirada' AFTER order_type;
  END IF;
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'address_id') = 0 THEN
    ALTER TABLE orders ADD COLUMN address_id INT UNSIGNED NULL AFTER delivery_type;
  END IF;
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'fk_orders_address') = 0 THEN
    ALTER TABLE orders ADD CONSTRAINT fk_orders_address FOREIGN KEY (address_id) REFERENCES user_addresses(id) ON DELETE SET NULL;
  END IF;
END//
DELIMITER ;
CALL add_orders_delivery_columns();
DROP PROCEDURE add_orders_delivery_columns;
