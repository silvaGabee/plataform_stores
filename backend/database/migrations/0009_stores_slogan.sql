-- Slogan da loja (texto abaixo do nome na vitrine).

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'slogan') = 0,
    'ALTER TABLE stores ADD COLUMN slogan VARCHAR(160) NULL DEFAULT NULL AFTER name',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
