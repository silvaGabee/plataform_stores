-- Campos de venda de balcão (PDV).
--
-- discount           desconto concedido no caixa. `total` continua sendo o que
--                    o cliente pagou (subtotal - desconto), então relatórios,
--                    BI e saldo do caixa não precisam mudar.
-- pdv_customer_name  nome digitado pelo operador. O pedido de PDV fica no nome
--                    de quem vendeu (customer_id), porque cliente de balcão
--                    não tem conta; sem esta coluna o nome se perdia.
--                    Não se chama `customer_name` porque várias consultas já
--                    fazem `o.*, u.name AS customer_name` e as duas colidiriam.
--
-- Idempotente, no mesmo padrão de 0013.

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND COLUMN_NAME = 'discount') = 0,
        'ALTER TABLE orders ADD COLUMN discount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND COLUMN_NAME = 'pdv_customer_name') = 0,
        'ALTER TABLE orders ADD COLUMN pdv_customer_name VARCHAR(120) NULL DEFAULT NULL AFTER discount',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
