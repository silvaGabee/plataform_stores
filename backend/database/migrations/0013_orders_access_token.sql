-- Token de acesso ao pedido.
--
-- A página /loja/{slug}/pedido/{id} validava só que o pedido era da loja, não
-- que era de quem estava olhando — e os ids são sequenciais. O token torna o
-- link impossível de adivinhar, e é o que permite ao cliente rever o próprio
-- comprovante sem depender de continuar logado.
--
-- Idempotente e executável via PDO: usa PREPARE/EXECUTE em vez de
-- CREATE PROCEDURE + DELIMITER (DELIMITER é diretiva do cliente mysql, não do
-- servidor — era por isso que as migrations antigas não rodavam por PDO).

SET @ddl = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND COLUMN_NAME = 'access_token') = 0,
        'ALTER TABLE orders ADD COLUMN access_token CHAR(32) NULL DEFAULT NULL',
        'DO 0'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pedidos que já existiam ganham um token agora, senão ficariam inacessíveis
-- para o cliente que não estiver logado.
--
-- MD5(UUID()+RAND()) e não RANDOM_BYTES(): esta última só existe no MariaDB
-- 10.10+ e o ambiente de desenvolvimento roda 10.4. Não é um CSPRNG — serve
-- para o backfill de registros antigos. Pedidos novos recebem o token de
-- random_bytes() no PHP (OrderRepository::create), que é criptográfico.
UPDATE orders
   SET access_token = MD5(CONCAT(UUID(), RAND(), id, NOW(6)))
 WHERE access_token IS NULL;

SET @idx = (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'orders'
            AND INDEX_NAME = 'idx_access_token') = 0,
        'CREATE INDEX idx_access_token ON orders (access_token)',
        'DO 0'
    )
);
PREPARE stmt FROM @idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
