-- Ícone público da loja (foto na aba do navegador e ao lado do nome).

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'store_icon_path') = 0,
    'ALTER TABLE stores ADD COLUMN store_icon_path VARCHAR(512) NULL DEFAULT NULL AFTER banner_path',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
