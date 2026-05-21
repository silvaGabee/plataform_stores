-- Slogan da loja (texto abaixo do nome na vitrine). Execute no MySQL (idempotente).
USE plataform_stores;

DELIMITER //
CREATE PROCEDURE add_store_slogan_column()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'slogan') = 0 THEN
    ALTER TABLE stores ADD COLUMN slogan VARCHAR(160) NULL DEFAULT NULL AFTER name;
  END IF;
END//
DELIMITER ;
CALL add_store_slogan_column();
DROP PROCEDURE add_store_slogan_column;
