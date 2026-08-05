-- Categorias da vitrine (faixa de ícones no catálogo).

CREATE TABLE IF NOT EXISTS store_vitrine_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    icon_key VARCHAR(40) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_sort (store_id, sort_order),
    UNIQUE KEY uniq_store_name (store_id, name)
) ENGINE=InnoDB;
