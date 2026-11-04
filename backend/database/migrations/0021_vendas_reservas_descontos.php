<?php

/**
 * Vendas: reserva de estoque, cancelamento, descontos, cupons, parcelamento e
 * conferência de caixa.
 *
 * orders.expires_at          até quando um pedido online pendente segura o
 *                            estoque. Passou do prazo sem pagamento, o pedido é
 *                            cancelado (OrderService::expirarPendentes).
 * orders.coupon_code         cupom aplicado no checkout (o valor do desconto
 *                            continua em orders.discount).
 * orders.cancelled_at/_by    quando e quem cancelou (NULL = expiração automática).
 * products.promo_price       preço promocional definido pelo gerente.
 * payments.installments,
 * installment_amount,
 * interest_amount            parcelamento no cartão (tabela Price).
 * stores.installments_*      regra de parcelamento da loja.
 * cash_registers.expected_amount, difference, closed_by
 *                            conferência do fechamento de caixa.
 * coupons                    cupons de desconto da vitrine.
 * discount_requests          pedido de desconto do PDV aguardando o gerente.
 *
 * Idempotente: cada coluna/tabela só é criada se ainda não existir.
 */
return static function (PDO $pdo): void {
    $temColuna = static function (string $tabela, string $coluna) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$tabela, $coluna]);

        return (int) $st->fetchColumn() > 0;
    };
    $temIndice = static function (string $tabela, string $indice) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $st->execute([$tabela, $indice]);

        return (int) $st->fetchColumn() > 0;
    };
    $coluna = static function (string $tabela, string $coluna, string $definicao) use ($pdo, $temColuna): void {
        if (!$temColuna($tabela, $coluna)) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    };

    $coluna('orders', 'expires_at', 'DATETIME NULL DEFAULT NULL AFTER created_at');
    $coluna('orders', 'coupon_code', 'VARCHAR(40) NULL DEFAULT NULL AFTER discount');
    $coluna('orders', 'cancelled_at', 'DATETIME NULL DEFAULT NULL AFTER expires_at');
    $coluna('orders', 'cancelled_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER cancelled_at');
    if (!$temIndice('orders', 'idx_store_status_expires')) {
        $pdo->exec('CREATE INDEX idx_store_status_expires ON orders (store_id, status, expires_at)');
    }

    $coluna('products', 'promo_price', 'DECIMAL(12,2) NULL DEFAULT NULL AFTER sale_price');

    $coluna('payments', 'installments', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER amount');
    $coluna('payments', 'installment_amount', 'DECIMAL(12,2) NULL DEFAULT NULL AFTER installments');
    $coluna('payments', 'interest_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER installment_amount');

    $coluna('stores', 'installments_max', 'TINYINT UNSIGNED NOT NULL DEFAULT 6');
    $coluna('stores', 'installments_free', 'TINYINT UNSIGNED NOT NULL DEFAULT 3');
    $coluna('stores', 'installments_rate', 'DECIMAL(5,2) NOT NULL DEFAULT 1.99');
    $coluna('stores', 'installments_min_value', 'DECIMAL(10,2) NOT NULL DEFAULT 20.00');

    $coluna('cash_registers', 'expected_amount', 'DECIMAL(12,2) NULL DEFAULT NULL AFTER final_amount');
    $coluna('cash_registers', 'difference', 'DECIMAL(12,2) NULL DEFAULT NULL AFTER expected_amount');
    $coluna('cash_registers', 'closed_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER closed_at');

    $pdo->exec("CREATE TABLE IF NOT EXISTS `coupons` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `store_id` INT UNSIGNED NOT NULL,
        `code` VARCHAR(40) NOT NULL,
        `type` ENUM('percent','valor') NOT NULL DEFAULT 'percent',
        `value` DECIMAL(12,2) NOT NULL,
        `min_subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `max_uses` INT UNSIGNED NULL DEFAULT NULL,
        `uses` INT UNSIGNED NOT NULL DEFAULT 0,
        `ends_at` DATE NULL DEFAULT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_store_code` (`store_id`, `code`),
        CONSTRAINT `fk_coupons_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `discount_requests` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `store_id` INT UNSIGNED NOT NULL,
        `requested_by` INT UNSIGNED NOT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL,
        `discount` DECIMAL(12,2) NOT NULL,
        `reason` VARCHAR(255) NULL DEFAULT NULL,
        `items_summary` VARCHAR(500) NULL DEFAULT NULL,
        `status` ENUM('pendente','aprovado','recusado','usado','cancelado') NOT NULL DEFAULT 'pendente',
        `decided_by` INT UNSIGNED NULL DEFAULT NULL,
        `decided_at` DATETIME NULL DEFAULT NULL,
        `order_id` INT UNSIGNED NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_store_status` (`store_id`, `status`),
        CONSTRAINT `fk_discount_requests_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pedidos pendentes antigos ganham prazo a partir da criação: o primeiro
    // expirarPendentes() os cancela, em vez de segurarem estoque para sempre.
    $pdo->exec("UPDATE orders SET expires_at = created_at + INTERVAL 30 MINUTE WHERE status = 'pendente' AND expires_at IS NULL");
};
