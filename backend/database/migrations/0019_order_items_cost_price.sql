-- Custo congelado no item do pedido.
--
-- O preço de venda sempre foi gravado no item (order_items.price), mas o custo
-- não: o BI calculava o lucro com products.cost_price, o custo de HOJE. Mudar
-- o custo de um produto reescrevia o lucro de todos os meses anteriores.
--
-- Agora o custo da venda é fotografado no item, como o preço. Pedidos antigos
-- recebem o custo atual uma única vez — é a melhor informação que existe, já
-- que o custo da época nunca foi guardado. A partir daqui, o passado não muda.
--
-- NULL continua possível (item de produto sem custo cadastrado na época, ou
-- produto apagado antes do backfill); o BI trata como custo zero, igual antes.
--
-- Idempotente, no mesmo padrão de 0013.

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'order_items'
            AND COLUMN_NAME = 'cost_price') = 0,
        'ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(12,2) NULL DEFAULT NULL AFTER price',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE order_items oi
  JOIN products p ON p.id = oi.product_id
   SET oi.cost_price = p.cost_price
 WHERE oi.cost_price IS NULL;
