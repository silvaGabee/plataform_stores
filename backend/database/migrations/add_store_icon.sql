-- Ícone público da loja (foto na aba e ao lado do nome). Execute no MySQL (idempotente).
USE plataform_stores;

DELIMITER //
CREATE PROCEDURE add_store_icon_column()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'store_icon_path') = 0 THEN
    ALTER TABLE stores ADD COLUMN store_icon_path VARCHAR(512) NULL DEFAULT NULL AFTER banner_path;
  END IF;
END//
DELIMITER ;
CALL add_store_icon_column();
DROP PROCEDURE add_store_icon_column;
