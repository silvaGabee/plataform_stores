<?php

/**
 * Dados da empresa no rodapé da vitrine.
 *
 * stores.legal_name   razão social
 * stores.document     CNPJ (ou CPF de quem vende como pessoa física), só dígitos
 * stores.address      endereço da loja, em texto livre
 *
 * O Decreto 7.962/2013 (comércio eletrônico) pede que a loja mostre nome
 * empresarial, CNPJ/CPF e endereço em local de destaque. Antes o rodapé tinha
 * só "Nome da loja © ano".
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

    $coluna('stores', 'legal_name', 'VARCHAR(160) NULL DEFAULT NULL AFTER `phone`');
    $coluna('stores', 'document', 'VARCHAR(14) NULL DEFAULT NULL AFTER `legal_name`');
    $coluna('stores', 'address', 'VARCHAR(255) NULL DEFAULT NULL AFTER `document`');
};
