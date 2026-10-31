-- =====================================================================
--  SaaS multi-tenant para clínicas de exames — BANCO CENTRAL (plataforma)
--  [Confirmado] Versões de destino: docs/fluxos/14-arquitetura-laravel.md, seção 14.1.
--  [Confirmado] DDL existente: InnoDB · utf8mb4 / utf8mb4_0900_ai_ci.
--  [Confirmado] Homologação em MySQL/MariaDB de destino pendente; ver CHANGELOG.md.
--  Guarda tenants, planos, assinaturas e dados do super-admin.
--  NÃO guarda dado clínico (isso vive no banco de cada tenant — ver tenant.sql).
--  Convenções: id BIGINT PK; public_id CHAR(26) ULID para URL/API;
--  enums como VARCHAR (nunca ENUM MySQL); dinheiro em DECIMAL;
--  soft delete (deleted_at) e auditoria (created_by/updated_by) onde faz sentido.
--  Gerado em 31/10/2026. Referência de modelo: docs 09, 10, 12.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Staff da plataforma (super-admins) — autenticação do painel central
-- ---------------------------------------------------------------------
CREATE TABLE `platform_users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`         CHAR(26) NOT NULL,
  `name`              VARCHAR(180) NOT NULL,
  `email`             VARCHAR(180) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `password`          VARCHAR(255) NOT NULL,
  `two_factor_secret` TEXT NULL,
  `two_factor_recovery_codes` TEXT NULL,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `remember_token`    VARCHAR(100) NULL,
  `created_at`        TIMESTAMP NULL,
  `updated_at`        TIMESTAMP NULL,
  `deleted_at`        TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platform_users_public` (`public_id`),
  UNIQUE KEY `uq_platform_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- Tenants (clínicas) e seus domínios/subdomínios
-- ---------------------------------------------------------------------
CREATE TABLE `tenants` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`      CHAR(26) NOT NULL,
  `name`           VARCHAR(180) NOT NULL,
  `slug`           VARCHAR(120) NOT NULL,                 -- clinica.plataforma.com.br
  `db_connection`  VARCHAR(120) NOT NULL,                 -- nome lógico da conexão/banco do tenant
  `db_name`        VARCHAR(120) NOT NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'trial',  -- trial|active|past_due|read_only|suspended
  `is_dedicated`   TINYINT(1) NOT NULL DEFAULT 0,         -- enterprise em instância própria
  `region`         VARCHAR(40) NULL,
  `trial_ends_at`  DATE NULL,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_public` (`public_id`),
  UNIQUE KEY `uq_tenants_slug` (`slug`),
  UNIQUE KEY `uq_tenants_db` (`db_name`),
  KEY `ix_tenants_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `domains` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `domain`     VARCHAR(190) NOT NULL,                     -- subdomínio ou domínio próprio
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domains_domain` (`domain`),
  KEY `ix_domains_tenant` (`tenant_id`),
  CONSTRAINT `fk_domains_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- Planos, features e preço
-- ---------------------------------------------------------------------
CREATE TABLE `plans` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`   CHAR(26) NOT NULL,
  `code`        VARCHAR(60) NOT NULL,                     -- starter|pro|enterprise
  `name`        VARCHAR(120) NOT NULL,
  `cycle`       VARCHAR(12) NOT NULL DEFAULT 'monthly',   -- monthly|yearly
  `price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency`    CHAR(3) NOT NULL DEFAULT 'BRL',
  `trial_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_public` (`public_id`),
  UNIQUE KEY `uq_plans_code_cycle` (`code`,`cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `plan_features` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id`    BIGINT UNSIGNED NOT NULL,
  `feature`    VARCHAR(80) NOT NULL,                      -- units_max|users_max|exams_month|portal_empresa|icp_sign|whatsapp...
  `value`      VARCHAR(120) NOT NULL,                     -- limite ou 'on'/'off'
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_feature` (`plan_id`,`feature`),
  CONSTRAINT `fk_plan_features_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- Assinaturas, itens, faturas, pagamentos, uso, cupons, webhooks
-- ---------------------------------------------------------------------
CREATE TABLE `subscriptions` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`            CHAR(26) NOT NULL,
  `tenant_id`            BIGINT UNSIGNED NOT NULL,
  `plan_id`              BIGINT UNSIGNED NOT NULL,
  `gateway`              VARCHAR(30) NOT NULL DEFAULT 'asaas',
  `gateway_customer_id`  VARCHAR(80) NULL,
  `gateway_subscription_id` VARCHAR(80) NULL,
  `status`               VARCHAR(20) NOT NULL DEFAULT 'trialing', -- trialing|active|past_due|read_only|suspended|canceled
  `trial_ends_at`        DATE NULL,
  `current_period_start` DATE NULL,
  `current_period_end`   DATE NULL,
  `canceled_at`          DATETIME NULL,
  `created_at`           TIMESTAMP NULL,
  `updated_at`           TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriptions_public` (`public_id`),
  KEY `ix_subscriptions_tenant` (`tenant_id`),
  KEY `ix_subscriptions_status` (`status`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan`   FOREIGN KEY (`plan_id`)   REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `subscription_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id` BIGINT UNSIGNED NOT NULL,
  `addon`           VARCHAR(80) NOT NULL,                 -- add-on por feature
  `quantity`        INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_sub_items_sub` (`subscription_id`),
  CONSTRAINT `fk_sub_items_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `invoices` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`        CHAR(26) NOT NULL,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `subscription_id`  BIGINT UNSIGNED NULL,
  `gateway_invoice_id` VARCHAR(80) NULL,
  `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency`         CHAR(3) NOT NULL DEFAULT 'BRL',
  `status`           VARCHAR(20) NOT NULL DEFAULT 'open', -- open|paid|overdue|canceled|refunded
  `due_date`         DATE NULL,
  `paid_at`          DATETIME NULL,
  `nfse_number`      VARCHAR(60) NULL,                    -- NFS-e da assinatura
  `nfse_url`         VARCHAR(512) NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_public` (`public_id`),
  KEY `ix_invoices_tenant` (`tenant_id`),
  KEY `ix_invoices_status` (`status`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_sub`    FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `payments` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`       BIGINT UNSIGNED NOT NULL,
  `gateway_payment_id` VARCHAR(80) NULL,
  `method`           VARCHAR(20) NOT NULL,                -- pix|boleto|card
  `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status`           VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|confirmed|failed|refunded
  `paid_at`          DATETIME NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `usage_records` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `metric`     VARCHAR(40) NOT NULL,                      -- exams|appointments|storage_mb
  `quantity`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `period`     CHAR(7) NOT NULL,                          -- YYYY-MM
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage` (`tenant_id`,`metric`,`period`),
  CONSTRAINT `fk_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `coupons` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(40) NOT NULL,
  `percent_off` DECIMAL(5,2) NULL,
  `amount_off`  DECIMAL(12,2) NULL,
  `valid_until` DATE NULL,
  `max_redemptions` INT UNSIGNED NULL,
  `redeemed`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `webhook_events` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway`     VARCHAR(30) NOT NULL,
  `event_id`    VARCHAR(120) NOT NULL,                    -- idempotência
  `type`        VARCHAR(80) NOT NULL,
  `payload`     JSON NULL,
  `processed_at` DATETIME NULL,
  `created_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_event` (`gateway`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Auditoria do painel central (concessão de acesso, provisionamento de tenant)
CREATE TABLE `audit_log_central` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform_user_id` BIGINT UNSIGNED NULL,
  `action`       VARCHAR(80) NOT NULL,
  `subject_type` VARCHAR(120) NULL,
  `subject_id`   BIGINT UNSIGNED NULL,
  `ip`           VARCHAR(45) NULL,
  `meta`         JSON NULL,
  `created_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_audit_central_subject` (`subject_type`,`subject_id`),
  CONSTRAINT `fk_audit_central_user` FOREIGN KEY (`platform_user_id`) REFERENCES `platform_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
