-- Fecha a unificação de identidade: um e-mail, uma pessoa, uma senha.
--
-- Depende de 0015 (store_members) e 0016 (consolidação dos duplicados). Rodar
-- esta antes daquelas falha no índice único, que é o comportamento desejado —
-- é melhor parar do que apagar dados sem querer.
--
-- store_id e user_type saem de users: o vínculo com a loja agora é
-- store_members, e "cliente" passa a ser simplesmente quem não tem vínculo.
-- Mantê-las seria conservar uma segunda fonte de verdade — exatamente a
-- confusão que esta fase existe para eliminar.

-- UNIQUE de verdade. O antigo era (email, store_id), e como NULL nunca colide
-- com NULL no MySQL, nada impedia duas contas de plataforma com o mesmo e-mail:
-- a checagem existia só no PHP, sujeita a corrida.
ALTER TABLE users DROP INDEX unique_email_store;
ALTER TABLE users ADD UNIQUE KEY uniq_email (email);

-- A FK precisa sair antes da coluna.
SET @fk = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'store_id'
       AND REFERENCED_TABLE_NAME = 'stores'
     LIMIT 1
);
SET @ddl = IF(@fk IS NULL, 'DO 0', CONCAT('ALTER TABLE users DROP FOREIGN KEY ', @fk));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE users DROP INDEX idx_store', 'DO 0')
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_store'
);
PREPARE stmt FROM @idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE users DROP COLUMN store_id;
ALTER TABLE users DROP COLUMN user_type;
