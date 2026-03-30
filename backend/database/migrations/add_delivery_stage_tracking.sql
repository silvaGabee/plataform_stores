-- Estágios de entrega e código de rastreio
-- Execute no banco plataform_stores (idempotente)

USE plataform_stores;

DELIMITER //
CREATE PROCEDURE add_delivery_stage_columns()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_stage') = 0 THEN
    ALTER TABLE orders ADD COLUMN delivery_stage ENUM('solicitado','empacotando','entregue_transportadora','em_rota','entregue') NOT NULL DEFAULT 'solicitado' AFTER address_id;
  END IF;
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'tracking_code') = 0 THEN
    ALTER TABLE orders ADD COLUMN tracking_code VARCHAR(100) DEFAULT NULL AFTER delivery_stage;
  END IF;
END//
DELIMITER ;
CALL add_delivery_stage_columns();
DROP PROCEDURE add_delivery_stage_columns;
