<?php

/**
 * Moderação de avaliações pelo gerente.
 *
 * product_reviews.reply / replied_at / replied_by   resposta pública da loja.
 * product_reviews.hidden_at / hidden_by             avaliação removida da vitrine.
 *
 * "Remover" oculta, não apaga: a nota sai da vitrine e da média, mas o gerente
 * pode desfazer, e o cliente não consegue reaparecer avaliando de novo (a
 * linha continua lá e a nova nota a substitui, ainda oculta).
 *
 * Idempotente: cada coluna só é criada se ainda não existir.
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

    $coluna('product_reviews', 'reply', 'VARCHAR(1000) NULL DEFAULT NULL AFTER `comment`');
    $coluna('product_reviews', 'replied_at', 'DATETIME NULL DEFAULT NULL AFTER `reply`');
    $coluna('product_reviews', 'replied_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `replied_at`');
    $coluna('product_reviews', 'hidden_at', 'DATETIME NULL DEFAULT NULL AFTER `replied_by`');
    $coluna('product_reviews', 'hidden_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `hidden_at`');
};
