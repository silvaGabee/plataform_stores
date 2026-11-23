<?php

/**
 * Qual loja criou a conta: users.created_by_store_id.
 *
 * A pessoa é uma conta só na plataforma, mas a tela Funcionários deixava o
 * gerente trocar nome, e-mail e senha de qualquer membro da equipe. Como
 * qualquer pessoa cadastrada pode abrir uma loja, bastava vincular o e-mail de
 * alguém à própria loja e trocar a senha para entrar como essa pessoa, com as
 * lojas dela junto. Agora o painel só mexe nesses dados quando a conta foi
 * criada pela própria loja e a pessoa não está em outra equipe.
 *
 * Contas que já existiam: ficam marcadas como criadas pela loja as que estão
 * numa equipe só, como funcionário, e nunca compraram como cliente na vitrine
 * (venda de PDV grava o operador como customer_id, então não conta) — o perfil
 * de quem foi cadastrado pelo gerente. As demais ficam sem marca.
 *
 * Idempotente: a coluna, a chave e a marcação só são feitas se faltarem.
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute(['users', 'created_by_store_id']);
    if ((int) $st->fetchColumn() > 0) {
        return;
    }

    $pdo->exec('ALTER TABLE `users` ADD COLUMN `created_by_store_id` INT UNSIGNED NULL DEFAULT NULL AFTER `password`');
    $pdo->exec('ALTER TABLE `users` ADD CONSTRAINT `fk_users_created_by_store` FOREIGN KEY (`created_by_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL');

    $pdo->exec(
        "UPDATE users u
           JOIN (SELECT user_id, MIN(store_id) AS store_id
                   FROM store_members
                  GROUP BY user_id
                 HAVING COUNT(*) = 1 AND MIN(role) = 'funcionario') m ON m.user_id = u.id
            SET u.created_by_store_id = m.store_id
          WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = u.id AND o.order_type = 'online')"
    );
};
