<?php

/**
 * Cópia do endereço de entrega no próprio pedido: orders.delivery_address.
 *
 * O pedido guardava só address_id, apontando para o cadastro do cliente
 * (ON DELETE SET NULL). Se o cliente editasse o endereço depois da compra, o
 * comprovante e a loja passavam a ver o endereço novo; se excluísse, o pedido
 * ficava sem endereço nenhum. Agora a compra grava uma cópia (JSON com rua,
 * número, complemento, bairro, cidade, UF e CEP) que não muda mais.
 *
 * Pedidos que já existiam recebem a cópia do endereço que ainda está
 * cadastrado; os que já perderam o endereço ficam sem.
 *
 * Idempotente: a coluna só é criada se faltar, e só pedidos sem cópia são
 * preenchidos.
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute(['orders', 'delivery_address']);
    if ((int) $st->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `orders` ADD COLUMN `delivery_address` TEXT NULL DEFAULT NULL AFTER `address_id`');
    }

    $rows = $pdo->query(
        'SELECT o.id, a.street, a.number, a.complement, a.neighborhood, a.city, a.state, a.zipcode
           FROM orders o
           JOIN user_addresses a ON a.id = o.address_id
          WHERE o.delivery_address IS NULL'
    )->fetchAll(PDO::FETCH_ASSOC);
    $up = $pdo->prepare('UPDATE orders SET delivery_address = ? WHERE id = ?');
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        unset($r['id']);
        $up->execute([json_encode($r, JSON_UNESCAPED_UNICODE), $id]);
    }
};
