-- Adiciona coluna background_color na tabela stores
-- Cor de fundo da vitrine da loja (formato hex: #RRGGBB)

ALTER TABLE stores
ADD COLUMN background_color VARCHAR(7) DEFAULT NULL COMMENT 'Cor de fundo da vitrine em hex (#RRGGBB)' AFTER store_icon_path;