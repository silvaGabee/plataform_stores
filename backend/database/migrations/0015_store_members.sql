-- Vínculo entre pessoa e loja.
--
-- Até aqui, o cargo morava em users.store_id + users.user_type — ou seja, a
-- mesma pessoa precisava de uma LINHA NOVA em users para cada loja onde
-- trabalhasse, cada uma com sua própria senha. Agora a pessoa é uma só e o
-- vínculo é um registro à parte.
--
-- 'cliente' deixa de ser cargo: quem compra simplesmente não tem vínculo.

CREATE TABLE IF NOT EXISTS store_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NOT NULL,
    role ENUM('gerente','funcionario') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_store (user_id, store_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_store_role (store_id, role)
) ENGINE=InnoDB;

-- Traz os vínculos que hoje estão embutidos em users.
INSERT IGNORE INTO store_members (user_id, store_id, role)
SELECT id, store_id, user_type
  FROM users
 WHERE store_id IS NOT NULL
   AND user_type IN ('gerente', 'funcionario');
