-- Banner da vitrine (imagem entre o hero e o catálogo). Execute no MySQL (idempotente).
USE plataform_stores;

DELIMITER //
CREATE PROCEDURE add_store_banner_column()
BEGIN
  IF (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'banner_path') = 0 THEN
    ALTER TABLE stores ADD COLUMN banner_path VARCHAR(512) NULL DEFAULT NULL AFTER phone;
  END IF;
END//
DELIMITER ;
CALL add_store_banner_column();
DROP PROCEDURE add_store_banner_column;
