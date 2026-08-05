-- Variações de produto (tamanho, numeração, cor) com estoque por item
CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_type VARCHAR(20) NOT NULL,
    variant_value VARCHAR(40) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_product_variant (product_id, variant_type, variant_value),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;
