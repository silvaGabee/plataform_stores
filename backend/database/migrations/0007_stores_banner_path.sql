-- Banner da vitrine (imagem entre o hero e o catálogo).

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'banner_path') = 0,
    'ALTER TABLE stores ADD COLUMN banner_path VARCHAR(512) NULL DEFAULT NULL AFTER phone',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
