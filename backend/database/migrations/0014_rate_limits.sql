-- Contadores de tentativa para limitar força bruta no login e abuso da API de IA.
--
-- Em tabela, e não em sessão: quem ataca não reaproveita cookie, então um
-- contador na sessão contaria sempre 1. A chave junta ação, IP e alvo.

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(191) NOT NULL COMMENT 'ex.: login:203.0.113.7:alguem@exemplo.com',
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uniq_bucket (bucket),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;
