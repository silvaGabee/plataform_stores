<?php

/**
 * Estorno de venda paga e código de barras.
 *
 * orders.status / payments.status   ganham 'estornado'. O BI só soma 'pago', então
 *                                   a venda estornada sai da receita e do lucro.
 * orders.refunded_*                 quando, por quem e por que foi estornado.
 * products.barcode                  código do produto sem variação (EAN ou SKU).
 * product_variants.barcode          na prática cada tamanho tem o próprio código.
 *
 * Idempotente: cada alteração só é feita se ainda não existir.
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
    $temIndice = static function (string $tabela, string $indice) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $st->execute([$tabela, $indice]);

        return (int) $st->fetchColumn() > 0;
    };

    $pdo->exec("ALTER TABLE `orders` MODIFY `status` ENUM('pendente','pago','cancelado','enviado','estornado') NOT NULL DEFAULT 'pendente'");
    $pdo->exec("ALTER TABLE `payments` MODIFY `status` ENUM('pendente','confirmado','cancelado','estornado') NOT NULL DEFAULT 'pendente'");
    $coluna('orders', 'refunded_at', 'DATETIME NULL DEFAULT NULL AFTER `cancelled_by`');
    $coluna('orders', 'refunded_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `refunded_at`');
    $coluna('orders', 'refund_reason', 'VARCHAR(255) NULL DEFAULT NULL AFTER `refunded_by`');

    $coluna('products', 'barcode', 'VARCHAR(40) NULL DEFAULT NULL AFTER `name`');
    if (!$temIndice('products', 'uniq_store_barcode')) {
        $pdo->exec('ALTER TABLE `products` ADD UNIQUE KEY `uniq_store_barcode` (`store_id`, `barcode`)');
    }
    $coluna('product_variants', 'barcode', 'VARCHAR(40) NULL DEFAULT NULL AFTER `variant_value`');
    if (!$temIndice('product_variants', 'idx_barcode')) {
        $pdo->exec('ALTER TABLE `product_variants` ADD KEY `idx_barcode` (`barcode`)');
    }
};
