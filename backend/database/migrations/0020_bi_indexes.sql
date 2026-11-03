-- Índices para as consultas do Analyzing BI.
--
-- Medido com backend/tools/bi_benchmark.php (50 mil pedidos, 124 mil itens):
--
--   idx_order_cover   lucro estimado: 1.245 ms → 459 ms.
--                     O índice contém todas as colunas que o cálculo do lucro
--                     lê do item; o banco responde só pelo índice ("Using
--                     index"), sem buscar a linha da tabela. Serve também à
--                     subconsulta do rateio do desconto.
--   idx_store_status_created
--                     soma do faturamento por período: 131 ms → 61 ms.
--                     Todas as consultas do BI filtram exatamente por loja,
--                     status e intervalo de datas, nesta ordem.
--
-- Idempotente, no mesmo padrão de 0013.

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'order_items'
            AND INDEX_NAME = 'idx_order_cover') = 0,
        'CREATE INDEX idx_order_cover ON order_items (order_id, product_id, quantity, price, cost_price)',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND INDEX_NAME = 'idx_store_status_created') = 0,
        'CREATE INDEX idx_store_status_created ON orders (store_id, status, created_at)',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
