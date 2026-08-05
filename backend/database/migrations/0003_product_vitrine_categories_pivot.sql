-- Produto pode pertencer a várias categorias da vitrine
CREATE TABLE IF NOT EXISTS product_vitrine_categories (
    product_id INT UNSIGNED NOT NULL,
    vitrine_category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, vitrine_category_id),
    CONSTRAINT fk_pvc_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_pvc_category
        FOREIGN KEY (vitrine_category_id) REFERENCES store_vitrine_categories(id) ON DELETE CASCADE,
    INDEX idx_pvc_category (vitrine_category_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO product_vitrine_categories (product_id, vitrine_category_id)
SELECT id, vitrine_category_id
FROM products
WHERE vitrine_category_id IS NOT NULL;
