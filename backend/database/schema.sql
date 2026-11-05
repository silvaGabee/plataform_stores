-- Plataforma Multi-Loja — schema completo.
--
-- Gerado a partir do banco de desenvolvimento com:
--   mysqldump -u root --no-data --skip-comments plataform_stores
--
-- Este arquivo é o RETRATO ATUAL do schema, não o histórico. Ele já inclui
-- tudo o que as migrations aplicam, e por isso termina registando todas elas
-- como aplicadas em `schema_migrations` — uma instalação nova sai daqui já em
-- dia, sem tentar recriar colunas que já existem.
--
-- Instalação nova:
--   1) mysql -u root < backend/database/schema.sql
--   2) php backend/scripts/migrate.php --status   (deve dizer "atualizado")
--
-- Num banco que JÁ EXISTE, não execute este arquivo: use o migrate.php.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS plataform_stores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plataform_stores;


CREATE TABLE `cash_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cash_register_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `type` enum('entrada','saida') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cash_register` (`cash_register_id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `cash_movements_ibfk_1` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_movements_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `cash_registers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `opened_by` int(10) unsigned NOT NULL,
  `initial_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(12,2) DEFAULT NULL,
  `expected_amount` decimal(12,2) DEFAULT NULL,
  `difference` decimal(12,2) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opened_by` (`opened_by`),
  KEY `idx_store` (`store_id`),
  KEY `idx_opened` (`opened_at`),
  CONSTRAINT `cash_registers_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_registers_ibfk_2` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `type` enum('percent','valor') NOT NULL DEFAULT 'percent',
  `value` decimal(12,2) NOT NULL,
  `min_subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_uses` int(10) unsigned DEFAULT NULL,
  `uses` int(10) unsigned NOT NULL DEFAULT 0,
  `ends_at` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_code` (`store_id`,`code`),
  CONSTRAINT `fk_coupons_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `discount_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `requested_by` int(10) unsigned NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `items_summary` varchar(500) DEFAULT NULL,
  `status` enum('pendente','aprovado','recusado','usado','cancelado') NOT NULL DEFAULT 'pendente',
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_status` (`store_id`,`status`),
  CONSTRAINT `fk_discount_requests_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `employee_goals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `period` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `goal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_user_period` (`store_id`,`user_id`,`period`),
  KEY `user_id` (`user_id`),
  KEY `idx_store_period` (`store_id`,`period`),
  CONSTRAINT `employee_goals_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_goals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `employee_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_role` (`role_id`),
  CONSTRAINT `employee_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variant_key` varchar(80) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_order_cover` (`order_id`,`product_id`,`quantity`,`price`,`cost_price`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `order_type` enum('online','pdv') NOT NULL,
  `delivery_type` enum('retirada','entrega') NOT NULL DEFAULT 'retirada',
  `address_id` int(10) unsigned DEFAULT NULL,
  `delivery_stage` enum('solicitado','empacotando','entregue_transportadora','em_rota','entregue') NOT NULL DEFAULT 'solicitado',
  `tracking_code` varchar(100) DEFAULT NULL,
  `status` enum('pendente','pago','cancelado','enviado') NOT NULL DEFAULT 'pendente',
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pix_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(40) DEFAULT NULL,
  `pdv_customer_name` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `access_token` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_store` (`store_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `fk_orders_address` (`address_id`),
  KEY `idx_access_token` (`access_token`),
  KEY `idx_store_status_created` (`store_id`,`status`,`created_at`),
  KEY `idx_store_status_expires` (`store_id`,`status`,`expires_at`),
  CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `user_addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `method` enum('dinheiro','cartao','pix') NOT NULL,
  `status` enum('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  `amount` decimal(12,2) NOT NULL,
  `installments` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `installment_amount` decimal(12,2) DEFAULT NULL,
  `interest_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pix_qr_code` text DEFAULT NULL,
  `card_holder` varchar(120) DEFAULT NULL,
  `card_last4` char(4) DEFAULT NULL,
  `card_brand` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `product_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `product_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_user` (`product_id`,`user_id`),
  KEY `idx_store_product` (`store_id`,`product_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `product_variants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `variant_type` varchar(20) NOT NULL,
  `variant_value` varchar(40) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_variant` (`product_id`,`variant_type`,`variant_value`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `product_vitrine_categories` (
  `product_id` int(10) unsigned NOT NULL,
  `vitrine_category_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`product_id`,`vitrine_category_id`),
  KEY `idx_pvc_category` (`vitrine_category_id`),
  CONSTRAINT `fk_pvc_category` FOREIGN KEY (`vitrine_category_id`) REFERENCES `store_vitrine_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pvc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `vitrine_category_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT 0.00,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `promo_price` decimal(12,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_vitrine_category` (`vitrine_category_id`),
  CONSTRAINT `fk_products_vitrine_category` FOREIGN KEY (`vitrine_category_id`) REFERENCES `store_vitrine_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bucket` varchar(191) NOT NULL COMMENT 'ex.: login:203.0.113.7:alguem@exemplo.com',
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bucket` (`bucket`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_role_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_parent` (`parent_role_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roles_ibfk_2` FOREIGN KEY (`parent_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `schema_migrations` (
  `version` varchar(191) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `type` enum('entrada','saida','ajuste','devolucao') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_dashboard_config` (
  `store_id` int(10) unsigned NOT NULL,
  `widgets_config` text DEFAULT NULL COMMENT 'JSON: [{id, type, title}, ...]',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`store_id`),
  CONSTRAINT `store_dashboard_config_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_goals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `period` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `goal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_period` (`store_id`,`period`),
  KEY `idx_store_period` (`store_id`,`period`),
  CONSTRAINT `store_goals_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `role` enum('gerente','funcionario') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_store` (`user_id`,`store_id`),
  KEY `idx_store_role` (`store_id`,`role`),
  CONSTRAINT `store_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_members_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_pix_configs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `pix_key` varchar(255) DEFAULT NULL,
  `pix_key_type` enum('cpf','cnpj','email','telefone','aleatoria') DEFAULT 'aleatoria',
  `merchant_name` varchar(255) DEFAULT NULL,
  `merchant_city` varchar(100) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_id` (`store_id`),
  KEY `idx_store` (`store_id`),
  CONSTRAINT `store_pix_configs_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_shipping_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `cep_start` char(8) NOT NULL,
  `cep_end` char(8) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `days` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_cep` (`store_id`,`cep_start`,`cep_end`),
  CONSTRAINT `fk_shipping_rules_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `store_vitrine_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `icon_key` varchar(40) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_name` (`store_id`,`name`),
  KEY `idx_store_sort` (`store_id`,`sort_order`),
  CONSTRAINT `store_vitrine_categories_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slogan` varchar(160) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `banner_path` varchar(512) DEFAULT NULL,
  `store_icon_path` varchar(512) DEFAULT NULL,
  `background_color` varchar(7) DEFAULT NULL COMMENT 'Cor de fundo da vitrine em hex (#RRGGBB)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `installments_max` tinyint(3) unsigned NOT NULL DEFAULT 6,
  `installments_free` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `installments_rate` decimal(5,2) NOT NULL DEFAULT 1.99,
  `installments_min_value` decimal(10,2) NOT NULL DEFAULT 20.00,
  `pix_discount_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `free_shipping_min` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `label` varchar(100) DEFAULT NULL COMMENT 'Ex: Casa, Trabalho',
  `street` varchar(255) NOT NULL,
  `number` varchar(20) NOT NULL,
  `complement` varchar(100) DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` char(2) NOT NULL,
  `zipcode` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_favorites` (
  `user_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`product_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Todas as migrations já estão contempladas acima.
INSERT INTO schema_migrations (version) VALUES ('0001_store_vitrine_categories.sql');
INSERT INTO schema_migrations (version) VALUES ('0002_products_vitrine_category_id.sql');
INSERT INTO schema_migrations (version) VALUES ('0003_product_vitrine_categories_pivot.sql');
INSERT INTO schema_migrations (version) VALUES ('0004_product_variants.sql');
INSERT INTO schema_migrations (version) VALUES ('0005_order_items_variant_key.sql');
INSERT INTO schema_migrations (version) VALUES ('0006_payments_card_meta.sql');
INSERT INTO schema_migrations (version) VALUES ('0007_stores_banner_path.sql');
INSERT INTO schema_migrations (version) VALUES ('0008_stores_icon_path.sql');
INSERT INTO schema_migrations (version) VALUES ('0009_stores_slogan.sql');
INSERT INTO schema_migrations (version) VALUES ('0010_stores_background_color.sql');
INSERT INTO schema_migrations (version) VALUES ('0011_delivery_and_addresses.sql');
INSERT INTO schema_migrations (version) VALUES ('0012_orders_delivery_stage.sql');
INSERT INTO schema_migrations (version) VALUES ('0013_orders_access_token.sql');
INSERT INTO schema_migrations (version) VALUES ('0014_rate_limits.sql');
INSERT INTO schema_migrations (version) VALUES ('0015_store_members.sql');
INSERT INTO schema_migrations (version) VALUES ('0017_users_single_identity.sql');
INSERT INTO schema_migrations (version) VALUES ('0016_consolidate_user_identity.php');
INSERT INTO schema_migrations (version) VALUES ('0018_orders_pdv_fields.sql');
INSERT INTO schema_migrations (version) VALUES ('0019_order_items_cost_price.sql');
INSERT INTO schema_migrations (version) VALUES ('0020_bi_indexes.sql');
INSERT INTO schema_migrations (version) VALUES ('0021_vendas_reservas_descontos.php');
INSERT INTO schema_migrations (version) VALUES ('0022_pdv_vendas_entregues.sql');
INSERT INTO schema_migrations (version) VALUES ('0023_vitrine_whatsapp_pix_frete_avaliacoes.php');
