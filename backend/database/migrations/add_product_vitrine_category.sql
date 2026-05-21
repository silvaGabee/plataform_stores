-- Liga produtos às categorias da vitrine
ALTER TABLE products
    ADD COLUMN vitrine_category_id INT UNSIGNED NULL DEFAULT NULL AFTER store_id,
    ADD INDEX idx_vitrine_category (vitrine_category_id),
    ADD CONSTRAINT fk_products_vitrine_category
        FOREIGN KEY (vitrine_category_id) REFERENCES store_vitrine_categories(id) ON DELETE SET NULL;
