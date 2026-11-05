<?php

/**
 * Vitrine: desconto à vista no PIX, frete por CEP, avaliações e favoritos.
 *
 * stores.pix_discount_pct     desconto (%) para quem paga a compra online no PIX.
 * stores.free_shipping_min    frete grátis a partir deste subtotal (NULL = nunca).
 * orders.pix_discount         parte de orders.discount que veio do PIX. O total
 *                             do desconto continua em orders.discount, então o
 *                             faturamento e o lucro do BI já o enxergam.
 * orders.shipping_fee         frete cobrado (entra em orders.total).
 * store_shipping_rules        faixas de CEP com valor e prazo de entrega.
 * product_reviews             nota de 1 a 5 e comentário de quem comprou.
 * user_favorites              produtos favoritados pelo cliente.
 *
 * Idempotente: cada coluna/tabela só é criada se ainda não existir.
 */
return static function (PDO $pdo): void {
    $temColuna = static function (string $tabela, string $coluna) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$tabela, $coluna]);

        return (int) $st->fetchColumn() > 0;
    };
    $coluna = static function (string $tabela, string $coluna, string $definicao) use ($pdo, $temColuna): void {
        if (!$temColuna($tabela, $coluna)) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    };

    $coluna('stores', 'pix_discount_pct', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00');
    $coluna('stores', 'free_shipping_min', 'DECIMAL(10,2) NULL DEFAULT NULL');

    $coluna('orders', 'pix_discount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount');
    $coluna('orders', 'shipping_fee', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER pix_discount');

    $pdo->exec("CREATE TABLE IF NOT EXISTS `store_shipping_rules` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `store_id` INT UNSIGNED NOT NULL,
        `name` VARCHAR(80) NOT NULL,
        `cep_start` CHAR(8) NOT NULL,
        `cep_end` CHAR(8) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `days` TINYINT UNSIGNED NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_store_cep` (`store_id`, `cep_start`, `cep_end`),
        CONSTRAINT `fk_shipping_rules_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_reviews` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `store_id` INT UNSIGNED NOT NULL,
        `product_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `rating` TINYINT UNSIGNED NOT NULL,
        `comment` VARCHAR(1000) NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_product_user` (`product_id`, `user_id`),
        KEY `idx_store_product` (`store_id`, `product_id`),
        KEY `idx_user` (`user_id`),
        CONSTRAINT `fk_reviews_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_favorites` (
        `user_id` INT UNSIGNED NOT NULL,
        `product_id` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `product_id`),
        KEY `idx_product` (`product_id`),
        CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
