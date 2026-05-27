-- Estoque por variação (cor/tamanho): guardar a combinação vendida no item do pedido.
ALTER TABLE order_items
    ADD COLUMN variant_key VARCHAR(80) NULL DEFAULT NULL AFTER product_id;
