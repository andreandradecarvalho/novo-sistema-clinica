-- SaaS clinico e ocupacional: banco unico + tenant_id.
-- Contratos: docs/fluxos/09, 13, 22, 23 e 24; versoes no fluxo 14.
-- Referencia de modelagem; nao comprova aplicacao, criptografia ou conformidade.
-- Aplicar SOMENTE em banco descartavel local/staging; nunca em producao.
-- FKs compostas impedem pais de outro tenant nos vinculos suportados.
-- Dados globais/indiretos e excecoes ACL/auditoria seguem fluxo 09.
-- Exclusao de tenant nao e rotina de offboarding; preservar prontuario.
-- FKs RESTRICT exigem desvinculacao autorizada, sem apagar historico clinico.
-- Campos *_encrypted recebem ciphertext pela aplicacao, nao pelo DDL.
-- 03/11/2026: contrato e justificativas em docs/schema/CHANGELOG.md.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tenants` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`      CHAR(26) NOT NULL,
  `name`           VARCHAR(180) NOT NULL,
  `slug`           VARCHAR(120) NOT NULL,
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
  `status`         VARCHAR(20) NOT NULL DEFAULT 'trial',  -- trial|active|past_due|read_only|suspended
  `trial_ends_at`  DATE NULL,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_public` (`public_id`),
  UNIQUE KEY `uq_tenants_slug` (`slug`),
  KEY `ix_tenants_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plans` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`   CHAR(26) NOT NULL,
  `code`        VARCHAR(60) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `cycle`       VARCHAR(12) NOT NULL DEFAULT 'monthly',
  `price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency`    CHAR(3) NOT NULL DEFAULT 'BRL',
  `trial_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_public` (`public_id`),
  UNIQUE KEY `uq_plans_code_cycle` (`code`,`cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhook_events` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway`     VARCHAR(30) NOT NULL,
  `event_id`    VARCHAR(120) NOT NULL,
  `type`        VARCHAR(80) NOT NULL,
  `payload`     JSON NULL,
  `processed_at` DATETIME NULL,
  `created_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_event` (`gateway`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(125) NOT NULL,
  `guard_name`  VARCHAR(125) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NULL,
  `log_name`     VARCHAR(120) NULL,
  `description`  TEXT NOT NULL,
  `subject_type` VARCHAR(190) NULL,
  `subject_id`   BIGINT UNSIGNED NULL,
  `causer_type`  VARCHAR(190) NULL,
  `causer_id`    BIGINT UNSIGNED NULL,
  `event`        VARCHAR(60) NULL,
  `batch_uuid`   CHAR(36) NULL,
  `properties`   JSON NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_activity_tenant_subject` (`tenant_id`,`subject_type`,`subject_id`),
  KEY `ix_activity_causer` (`causer_type`,`causer_id`),
  KEY `ix_activity_log_name` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `domains` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `domain`     VARCHAR(190) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domains_domain` (`domain`),
  KEY `ix_domains_tenant` (`tenant_id`),
  CONSTRAINT `fk_domains_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plan_features` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id`    BIGINT UNSIGNED NOT NULL,
  `feature`    VARCHAR(80) NOT NULL,
  `value`      VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_feature` (`plan_id`,`feature`),
  CONSTRAINT `fk_plan_features_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscriptions` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`            CHAR(26) NOT NULL,
  `tenant_id`            BIGINT UNSIGNED NOT NULL,
  `plan_id`              BIGINT UNSIGNED NOT NULL,
  `gateway`              VARCHAR(30) NOT NULL DEFAULT 'asaas',
  `gateway_customer_id`  VARCHAR(80) NULL,
  `gateway_subscription_id` VARCHAR(80) NULL,
  `status`               VARCHAR(20) NOT NULL DEFAULT 'trialing',
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
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subscriptions_plan`   FOREIGN KEY (`plan_id`)   REFERENCES `plans` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_subscriptions_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usage_records` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `metric`     VARCHAR(40) NOT NULL,
  `quantity`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `period`     CHAR(7) NOT NULL,
  `created_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage` (`tenant_id`,`metric`,`period`),
  CONSTRAINT `fk_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NULL,                    -- NULL = role global do sistema
  `name`        VARCHAR(125) NOT NULL,
  `guard_name`  VARCHAR(125) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles` (`tenant_id`,`name`,`guard_name`),
  KEY `ix_roles_tenant` (`tenant_id`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `model_type`    VARCHAR(190) NOT NULL,
  `model_id`      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`,`tenant_id`),
  KEY `ix_mhp_model` (`model_id`,`model_type`),
  KEY `ix_mhp_tenant` (`tenant_id`),
  CONSTRAINT `fk_mhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `public_id`  CHAR(26) NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `city`       VARCHAR(120) NULL,
  `state`      CHAR(2) NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_public` (`public_id`),
  KEY `ix_units_tenant` (`tenant_id`),
  CONSTRAINT `fk_units_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_units_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `companies` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `public_id`   CHAR(26) NOT NULL,
  `parent_id`   BIGINT UNSIGNED NULL,
  `name`        VARCHAR(180) NOT NULL,
  `slug`        VARCHAR(180) NOT NULL,
  `cnpj`        CHAR(14) NULL,
  `type`        VARCHAR(20) NOT NULL DEFAULT 'cliente',
  `storage_prefix` VARCHAR(190) NULL,
  `requires_registration_number` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_id`   BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  `deleted_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_public` (`public_id`),
  UNIQUE KEY `uq_companies_tenant_slug` (`tenant_id`,`slug`),
  UNIQUE KEY `uq_companies_tenant_cnpj` (`tenant_id`,`cnpj`),
  KEY `ix_companies_tenant` (`tenant_id`),
  KEY `ix_companies_parent` (`parent_id`),
  KEY `ix_companies_legacy` (`tenant_id`,`legacy_id`),
  CONSTRAINT `fk_companies_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_companies_parent` FOREIGN KEY (`tenant_id`,`parent_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_companies_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_types` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `public_id`     CHAR(26) NOT NULL,
  `code`          VARCHAR(20) NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `scope`         VARCHAR(20) NOT NULL DEFAULT 'exame',
  `requires_fasting` TINYINT(1) NOT NULL DEFAULT 0,
  `default_duration_min` SMALLINT UNSIGNED NULL,
  `price`         DECIMAL(12,2) NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_types_public` (`public_id`),
  UNIQUE KEY `uq_exam_types_tenant_code` (`tenant_id`,`code`),
  KEY `ix_exam_types_tenant` (`tenant_id`),
  CONSTRAINT `fk_exam_types_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_exam_types_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `health_question_groups` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `code`      VARCHAR(40) NOT NULL,
  `name`      VARCHAR(120) NOT NULL,
  `ordem`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hqg_tenant_code` (`tenant_id`,`code`),
  KEY `ix_hqg_tenant` (`tenant_id`),
  CONSTRAINT `fk_hqg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_health_question_groups_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `measurement_types` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `code`        VARCHAR(40) NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `unit`        VARCHAR(20) NULL,
  `ref_min`     DECIMAL(12,3) NULL,
  `ref_max`     DECIMAL(12,3) NULL,
  `ordem`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mtype_tenant_code` (`tenant_id`,`code`),
  KEY `ix_mtype_tenant` (`tenant_id`),
  CONSTRAINT `fk_mtype_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_measurement_types_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_templates` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `code`       VARCHAR(60) NOT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `channel`    VARCHAR(20) NOT NULL,
  `subject`    VARCHAR(200) NULL,
  `body`       MEDIUMTEXT NOT NULL,
  `applies_to` VARCHAR(12) NOT NULL DEFAULT 'all',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msgtpl_tenant_code` (`tenant_id`,`code`),
  KEY `ix_msgtpl_tenant` (`tenant_id`),
  CONSTRAINT `fk_msgtpl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_message_templates_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `key`       VARCHAR(120) NOT NULL,
  `value`     JSON NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_tenant_key` (`tenant_id`,`key`),
  KEY `ix_settings_tenant` (`tenant_id`),
  CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migration_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `importer_version` VARCHAR(80) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `watermark` JSON NULL,
  `summary` JSON NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_migration_runs_tenant` (`tenant_id`),
  UNIQUE KEY `uq_migration_runs_0` (`public_id`),
  CONSTRAINT `fk_migration_runs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_migration_runs_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscription_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subscription_id` BIGINT UNSIGNED NOT NULL,
  `addon`           VARCHAR(80) NOT NULL,
  `quantity`        INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_sub_items_sub` (`subscription_id`),
  CONSTRAINT `fk_sub_items_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`        CHAR(26) NOT NULL,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `subscription_id`  BIGINT UNSIGNED NULL,
  `gateway_invoice_id` VARCHAR(80) NULL,
  `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency`         CHAR(3) NOT NULL DEFAULT 'BRL',
  `status`           VARCHAR(20) NOT NULL DEFAULT 'open',
  `due_date`         DATE NULL,
  `paid_at`          DATETIME NULL,
  `nfse_number`      VARCHAR(60) NULL,
  `nfse_url`         VARCHAR(512) NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_public` (`public_id`),
  KEY `ix_invoices_tenant` (`tenant_id`),
  KEY `ix_invoices_status` (`status`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_sub`    FOREIGN KEY (`tenant_id`,`subscription_id`) REFERENCES `subscriptions` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_roles` (
  `role_id`    BIGINT UNSIGNED NOT NULL,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,                 -- team key
  `model_type` VARCHAR(190) NOT NULL,
  `model_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`,`tenant_id`),
  KEY `ix_mhr_model` (`model_id`,`model_type`),
  KEY `ix_mhr_tenant` (`tenant_id`),
  CONSTRAINT `fk_mhr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  CONSTRAINT `fk_rhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rhp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`          BIGINT UNSIGNED NOT NULL,
  `public_id`          CHAR(26) NOT NULL,
  `unit_id`            BIGINT UNSIGNED NULL,
  `name`               VARCHAR(180) NOT NULL,
  `email`              VARCHAR(180) NOT NULL,
  `email_verified_at`  DATETIME NULL,
  `password`           VARCHAR(255) NOT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `cpf_encrypted` TEXT NULL,
  `cpf_hash` CHAR(64) NULL,
  `crypto_key_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `two_factor_secret`  TEXT NULL,
  `two_factor_recovery_codes` TEXT NULL,
  `two_factor_confirmed_at` DATETIME NULL,
  `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`      DATETIME NULL,
  `last_login_ip`      VARCHAR(45) NULL,
  `legacy_id`          BIGINT UNSIGNED NULL,
  `remember_token`     VARCHAR(100) NULL,
  `created_at`         TIMESTAMP NULL,
  `updated_at`         TIMESTAMP NULL,
  `deleted_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_public` (`public_id`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  UNIQUE KEY `uq_users_tenant_cpf` (`tenant_id`,`cpf_hash`),
  KEY `ix_users_tenant` (`tenant_id`),
  KEY `ix_users_unit` (`unit_id`),
  KEY `ix_users_legacy` (`tenant_id`,`legacy_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_users_unit`   FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_users_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_units` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(180) NOT NULL,
  `city`       VARCHAR(120) NULL,
  `state`      CHAR(2) NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_company_units_tenant` (`tenant_id`),
  KEY `ix_company_units_company` (`company_id`,`is_active`),
  CONSTRAINT `fk_company_units_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_company_units_company` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_company_units_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_type_preparations` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `title`        VARCHAR(150) NOT NULL,
  `instructions` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prep_tenant` (`tenant_id`),
  KEY `ix_prep_exam` (`exam_type_id`),
  CONSTRAINT `fk_prep_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prep_exam`   FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_type_attachments` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `label`        VARCHAR(150) NOT NULL,
  `disk`         VARCHAR(40) NOT NULL DEFAULT 'tenant',
  `path`         VARCHAR(512) NOT NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_examatt_tenant` (`tenant_id`),
  KEY `ix_examatt_exam` (`exam_type_id`),
  CONSTRAINT `fk_examatt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_examatt_exam`   FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `health_questions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `group_id`   BIGINT UNSIGNED NOT NULL,
  `code`       VARCHAR(60) NOT NULL,
  `label`      VARCHAR(255) NOT NULL,
  `type`       VARCHAR(20) NOT NULL DEFAULT 'text',
  `options`    JSON NULL,
  `applies_to` VARCHAR(12) NOT NULL DEFAULT 'all',
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `version`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hq_tenant_code` (`tenant_id`,`code`,`version`),
  KEY `ix_hq_tenant` (`tenant_id`),
  KEY `ix_hq_group` (`group_id`),
  CONSTRAINT `fk_hq_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hq_group`  FOREIGN KEY (`tenant_id`,`group_id`) REFERENCES `health_question_groups` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_health_questions_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `unit_id`    BIGINT UNSIGNED NULL,
  `room_key`   VARCHAR(20) NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `pin_hash`   VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_tenant_key` (`tenant_id`,`room_key`),
  KEY `ix_rooms_tenant` (`tenant_id`),
  KEY `ix_rooms_unit` (`unit_id`),
  CONSTRAINT `fk_rooms_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rooms_unit`   FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_rooms_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `purpose` VARCHAR(30) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `last_used_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_devices_tenant` (`tenant_id`),
  UNIQUE KEY `uq_devices_0` (`token_hash`),
  CONSTRAINT `fk_devices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_devices_0` FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legacy_references` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `source_db` VARCHAR(64) NOT NULL,
  `source_table` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(190) NOT NULL,
  `target_type` VARCHAR(60) NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `match_method` VARCHAR(60) NULL,
  `confidence` DECIMAL(5,4) NULL,
  `row_hash` CHAR(64) NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_legacy_references_tenant` (`tenant_id`),
  UNIQUE KEY `uq_legacy_references_0` (`tenant_id`,`source_db`,`source_table`,`source_id`,`target_type`),
  CONSTRAINT `fk_legacy_references_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_legacy_references_0` FOREIGN KEY (`tenant_id`,`run_id`) REFERENCES `migration_runs` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_legacy_references_0` CHECK (`confidence` IS NULL OR `confidence` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migration_issues` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `source_db` VARCHAR(64) NOT NULL,
  `source_table` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(190) NOT NULL,
  `code` VARCHAR(80) NOT NULL,
  `severity` VARCHAR(20) NOT NULL,
  `resolution` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `details_encrypted` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_migration_issues_tenant` (`tenant_id`),
  CONSTRAINT `fk_migration_issues_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_migration_issues_0` FOREIGN KEY (`tenant_id`,`run_id`) REFERENCES `migration_runs` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`       BIGINT UNSIGNED NOT NULL,
  `gateway_payment_id` VARCHAR(80) NULL,
  `method`           VARCHAR(20) NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status`           VARCHAR(20) NOT NULL DEFAULT 'pending',
  `paid_at`          DATETIME NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `professionals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `council`     VARCHAR(10) NULL,
  `council_uf`  CHAR(2) NULL,
  `council_number` VARCHAR(20) NULL,
  `specialty`   VARCHAR(60) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professionals_user` (`user_id`),
  KEY `ix_professionals_tenant` (`tenant_id`),
  CONSTRAINT `fk_professionals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_professionals_user`   FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_professionals_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patients` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `public_id`        CHAR(26) NOT NULL,
  `user_id`          BIGINT UNSIGNED NULL,
  `company_id`       BIGINT UNSIGNED NULL,
  `company_unit_id`  BIGINT UNSIGNED NULL,
  `legacy_code`      VARCHAR(60) NULL,
  `legacy_id`        BIGINT UNSIGNED NULL,
  `name`             VARCHAR(180) NOT NULL,
  `normalized_name`  VARCHAR(180) NOT NULL,
  `cpf_encrypted` TEXT NULL,
  `cpf_hash` CHAR(64) NULL,
  `crypto_key_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `rg`               VARCHAR(30) NULL,
  `birth_date`       DATE NULL,
  `sex`              VARCHAR(12) NULL,
  `email`            VARCHAR(180) NULL,
  `mobile_phone`     VARCHAR(20) NULL,
  `home_phone`       VARCHAR(20) NULL,
  `work_phone`       VARCHAR(20) NULL,
  `registration_number` VARCHAR(50) NULL,
  `job_title`        VARCHAR(150) NULL,
  `occupation`       VARCHAR(150) NULL,
  `manager_name`     VARCHAR(180) NULL,
  `marital_status`   VARCHAR(30) NULL,
  `birthplace`       VARCHAR(150) NULL,
  `address_line`     VARCHAR(255) NULL,
  `district`         VARCHAR(120) NULL,
  `city`             VARCHAR(120) NULL,
  `state`            CHAR(2) NULL,
  `zipcode`          CHAR(8) NULL,
  `is_first_exam`    TINYINT(1) NOT NULL DEFAULT 1,
  `is_partial`       TINYINT(1) NOT NULL DEFAULT 0,
  `possible_duplicate_of_id` BIGINT UNSIGNED NULL,
  `terms_accepted_at`   DATETIME NULL,
  `terms_accepted_text` TEXT NULL,
  `terms_accepted_ip`   VARCHAR(45) NULL,
  `password`         VARCHAR(255) NULL,
  `remember_token`   VARCHAR(100) NULL,
  `portal_last_login_at` DATETIME NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  `deleted_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_patients_public` (`public_id`),
  UNIQUE KEY `uq_patients_tenant_cpf` (`tenant_id`,`cpf_hash`),
  KEY `ix_patients_tenant_norm_birth` (`tenant_id`,`normalized_name`,`birth_date`),
  KEY `ix_patients_tenant_company` (`tenant_id`,`company_id`,`deleted_at`),
  KEY `ix_patients_tenant_legacy_code` (`tenant_id`,`legacy_code`),
  KEY `ix_patients_tenant_legacy_id` (`tenant_id`,`legacy_id`),
  KEY `ix_patients_tenant_email` (`tenant_id`,`email`),
  KEY `ix_patients_dup` (`possible_duplicate_of_id`),
  FULLTEXT KEY `ft_patients_name` (`name`),
  CONSTRAINT `fk_patients_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patients_user`    FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patients_company` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patients_cunit`   FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patients_dup`     FOREIGN KEY (`tenant_id`,`possible_duplicate_of_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_patients_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `campaigns` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `public_id`   CHAR(26) NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `template_id` BIGINT UNSIGNED NULL,
  `status`      VARCHAR(20) NOT NULL DEFAULT 'rascunho',
  `scheduled_at` DATETIME NULL,
  `created_by`  BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaigns_public` (`public_id`),
  KEY `ix_campaigns_tenant` (`tenant_id`),
  KEY `ix_campaigns_template` (`template_id`),
  CONSTRAINT `fk_campaigns_tenant`   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_campaigns_template` FOREIGN KEY (`tenant_id`,`template_id`) REFERENCES `message_templates` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_campaigns_user`     FOREIGN KEY (`tenant_id`,`created_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_campaigns_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_user_access` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `scope` VARCHAR(30) NOT NULL DEFAULT 'occupational_status',
  `valid_from` DATETIME NOT NULL,
  `valid_until` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_company_user_access_tenant` (`tenant_id`),
  CONSTRAINT `fk_company_user_access_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_company_user_access_0` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_company_user_access_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_company_user_access_0` CHECK (`valid_until` IS NULL OR `valid_until` >= `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_merges` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`          BIGINT UNSIGNED NOT NULL,
  `survivor_patient_id` BIGINT UNSIGNED NOT NULL,
  `merged_patient_id`  BIGINT UNSIGNED NOT NULL,
  `merged_by`          BIGINT UNSIGNED NULL,
  `payload_snapshot`   JSON NULL,
  `reason`             TEXT NULL,
  `created_at`         TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_merges_tenant` (`tenant_id`),
  KEY `ix_merges_pair` (`survivor_patient_id`,`merged_patient_id`),
  CONSTRAINT `fk_merges_tenant`   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merges_survivor` FOREIGN KEY (`tenant_id`,`survivor_patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merges_merged`   FOREIGN KEY (`tenant_id`,`merged_patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_merges_user`     FOREIGN KEY (`tenant_id`,`merged_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       BIGINT UNSIGNED NOT NULL,
  `public_id`       CHAR(26) NOT NULL,
  `patient_id`      BIGINT UNSIGNED NOT NULL,
  `unit_id`         BIGINT UNSIGNED NULL,
  `company_id`      BIGINT UNSIGNED NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `scheduled_date`  DATE NOT NULL,
  `scheduled_at`    DATETIME NULL,
  `specialty`       VARCHAR(20) NOT NULL DEFAULT 'checkup',
  `status`          VARCHAR(20) NOT NULL DEFAULT 'pre_agendado',
  `is_priority`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_special`      TINYINT(1) NOT NULL DEFAULT 0,
  `has_gynecology`  TINYINT(1) NOT NULL DEFAULT 0,
  `snapshot_company_name` VARCHAR(180) NULL,
  `confirmed_at`    DATETIME NULL,
  `checked_in_at`   DATETIME NULL,
  `canceled_at`     DATETIME NULL,
  `notes`           TEXT NULL,
  `legacy_id`       BIGINT UNSIGNED NULL,
  `created_by`      BIGINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  `deleted_at`      TIMESTAMP NULL,
  -- Reservas canceladas/excluidas nao ocupam o slot; regras de capacidade no fluxo 22.
  `active_flag`     TINYINT UNSIGNED GENERATED ALWAYS AS (IF(`deleted_at` IS NULL AND `canceled_at` IS NULL AND `status` NOT IN ('cancelado','canceled'), 1, NULL)) STORED,
  `unit_scope` BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(`unit_id`, 0)) STORED,
  `slot_key` INT GENERATED ALWAYS AS (IFNULL(HOUR(`scheduled_at`) * 3600 + MINUTE(`scheduled_at`) * 60 + SECOND(`scheduled_at`), -1)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_public` (`public_id`),
  UNIQUE KEY `uq_appt_patient_date` (`tenant_id`,`patient_id`,`scheduled_date`,`unit_scope`,`slot_key`,`active_flag`),
  KEY `ix_appt_tenant_date_status` (`tenant_id`,`scheduled_date`,`status`),
  KEY `ix_appt_unit_date` (`unit_id`,`scheduled_date`),
  KEY `ix_appt_legacy` (`tenant_id`,`legacy_id`),
  CONSTRAINT `fk_appt_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_unit`    FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_company` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_cunit`   FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_createdby` FOREIGN KEY (`tenant_id`,`created_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_appt_date_time` CHECK (`scheduled_at` IS NULL OR DATE(`scheduled_at`) = `scheduled_date`),
  UNIQUE KEY `uq_appointments_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_company_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `registration_number` VARCHAR(50) NULL,
  `job_title` VARCHAR(150) NULL,
  `department` VARCHAR(150) NULL,
  `starts_on` DATE NULL,
  `ends_on` DATE NULL,
  `termination_date` DATE NULL,
  `source` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `context_encrypted` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_patient_company_links_tenant` (`tenant_id`),
  CONSTRAINT `fk_patient_company_links_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_company_links_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_company_links_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_company_links_2` FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_patient_company_links_0` CHECK (`ends_on` IS NULL OR `starts_on` IS NULL OR `ends_on` >= `starts_on`),
  UNIQUE KEY `uq_patient_company_links_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `purpose` VARCHAR(80) NOT NULL,
  `legal_basis` VARCHAR(80) NOT NULL,
  `policy_version` VARCHAR(40) NULL,
  `policy_text_hash` CHAR(64) NULL,
  `decision` VARCHAR(30) NOT NULL,
  `occurred_at` DATETIME NOT NULL,
  `recorded_by` BIGINT UNSIGNED NULL,
  `source` VARCHAR(60) NOT NULL,
  `evidence_encrypted` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_patient_consents_tenant` (`tenant_id`),
  CONSTRAINT `fk_patient_consents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_consents_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_consents_1` FOREIGN KEY (`tenant_id`,`recorded_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `requested_by` BIGINT UNSIGNED NULL,
  `payer_type` VARCHAR(20) NOT NULL DEFAULT 'patient',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `pricing_snapshot` JSON NULL,
  `context_encrypted` MEDIUMTEXT NULL,
  `authorized_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_exam_orders_tenant` (`tenant_id`),
  UNIQUE KEY `uq_exam_orders_0` (`public_id`),
  CONSTRAINT `fk_exam_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_2` FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_3` FOREIGN KEY (`tenant_id`,`requested_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_exam_orders_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `occupational_programs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `version` VARCHAR(40) NOT NULL,
  `responsible_professional_id` BIGINT UNSIGNED NULL,
  `valid_from` DATE NULL,
  `valid_until` DATE NULL,
  `source_reference` VARCHAR(190) NULL,
  `context_encrypted` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_occupational_programs_tenant` (`tenant_id`),
  UNIQUE KEY `uq_occupational_programs_0` (`tenant_id`,`company_id`,`version`),
  CONSTRAINT `fk_occupational_programs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_programs_0` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_programs_1` FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_programs_2` FOREIGN KEY (`tenant_id`,`responsible_professional_id`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_occupational_programs_0` CHECK (`valid_until` IS NULL OR `valid_from` IS NULL OR `valid_until` >= `valid_from`),
  UNIQUE KEY `uq_occupational_programs_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_match_candidates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `run_id` BIGINT UNSIGNED NOT NULL,
  `source_db` VARCHAR(64) NOT NULL,
  `source_table` VARCHAR(64) NOT NULL,
  `source_id` VARCHAR(190) NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `match_method` VARCHAR(60) NOT NULL,
  `confidence` DECIMAL(5,4) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_patient_match_candidates_tenant` (`tenant_id`),
  UNIQUE KEY `uq_patient_match_candidates_0` (`tenant_id`,`source_db`,`source_table`,`source_id`,`patient_id`),
  CONSTRAINT `fk_patient_match_candidates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_match_candidates_0` FOREIGN KEY (`tenant_id`,`run_id`) REFERENCES `migration_runs` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_match_candidates_1` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_patient_match_candidates_2` FOREIGN KEY (`tenant_id`,`reviewed_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `health_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `public_id`   CHAR(26) NOT NULL,
  `patient_id`  BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `filled_at`   DATETIME NULL,
  `filled_by`   BIGINT UNSIGNED NULL,
  `legacy_id`   BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  `deleted_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hr_public` (`public_id`),
  KEY `ix_hr_tenant_patient` (`tenant_id`,`patient_id`,`filled_at`),
  KEY `ix_hr_legacy` (`tenant_id`,`legacy_id`),
  CONSTRAINT `fk_hr_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hr_patient` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hr_appt`    FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hr_filledby` FOREIGN KEY (`tenant_id`,`filled_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_health_records_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_events` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NOT NULL,
  `type`           VARCHAR(30) NOT NULL,
  `channel`        VARCHAR(20) NULL,
  `occurred_at`    DATETIME NOT NULL,
  `user_id`        BIGINT UNSIGNED NULL,
  `meta`           JSON NULL,
  PRIMARY KEY (`id`),
  KEY `ix_apptev_tenant` (`tenant_id`),
  KEY `ix_apptev_appt` (`appointment_id`,`occurred_at`),
  CONSTRAINT `fk_apptev_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apptev_appt`   FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apptev_user`   FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_exams` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NOT NULL,
  `exam_type_id`   BIGINT UNSIGNED NOT NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'pendente',
  `room_id`        BIGINT UNSIGNED NULL,
  `started_at`     DATETIME NULL,
  `finished_at`    DATETIME NULL,
  `ordem`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_exam` (`appointment_id`,`exam_type_id`),
  KEY `ix_apptexam_tenant` (`tenant_id`),
  KEY `ix_apptexam_exam` (`exam_type_id`),
  KEY `ix_apptexam_room` (`room_id`),
  CONSTRAINT `fk_apptexam_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apptexam_appt`   FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apptexam_exam`   FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apptexam_room`   FOREIGN KEY (`tenant_id`,`room_id`) REFERENCES `rooms` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `room_states` (
  `room_id`       BIGINT UNSIGNED NOT NULL,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `status`        VARCHAR(12) NOT NULL DEFAULT 'livre',
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_label` VARCHAR(200) NULL,
  `updated_at`    DATETIME NOT NULL,
  PRIMARY KEY (`room_id`),
  KEY `ix_roomstate_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_roomstate_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_roomstate_room`   FOREIGN KEY (`tenant_id`,`room_id`) REFERENCES `rooms` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_roomstate_appt`   FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tv_calls` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `room_id`        BIGINT UNSIGNED NULL,
  `patient_label`  VARCHAR(200) NOT NULL,
  `room_label`     VARCHAR(100) NOT NULL,
  `called_at`      DATETIME NOT NULL,
  `released_at`    DATETIME NULL,
  `displayed`      TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_tvcalls_tenant_called` (`tenant_id`,`called_at`),
  KEY `ix_tvcalls_room` (`room_id`,`called_at`),
  KEY `ix_tvcalls_appt` (`appointment_id`),
  CONSTRAINT `fk_tvcalls_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tvcalls_room`   FOREIGN KEY (`tenant_id`,`room_id`) REFERENCES `rooms` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tvcalls_appt`   FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `campaign_id`  BIGINT UNSIGNED NULL,
  `template_id`  BIGINT UNSIGNED NULL,
  `patient_id`   BIGINT UNSIGNED NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `channel`      VARCHAR(20) NOT NULL,
  `to_address`   VARCHAR(190) NOT NULL,
  `status`       VARCHAR(20) NOT NULL DEFAULT 'queued',
  `provider_ref` VARCHAR(120) NULL,
  `idempotency_key` VARCHAR(120) NULL,
  `content_snapshot_encrypted` MEDIUMTEXT NULL,
  `error`        TEXT NULL,
  `sent_at`      DATETIME NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_messages_tenant_status` (`tenant_id`,`status`),
  KEY `ix_messages_patient` (`patient_id`),
  KEY `ix_messages_appt` (`appointment_id`),
  KEY `ix_messages_campaign` (`campaign_id`),
  CONSTRAINT `fk_messages_tenant`   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_campaign` FOREIGN KEY (`tenant_id`,`campaign_id`) REFERENCES `campaigns` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_template` FOREIGN KEY (`tenant_id`,`template_id`) REFERENCES `message_templates` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_patient`  FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_appt`     FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_message_idempotency` (`tenant_id`,`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `surveys` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `public_id`      CHAR(26) NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_id`     BIGINT UNSIGNED NULL,
  `nps`            TINYINT UNSIGNED NULL,
  `punctuality`    TINYINT UNSIGNED NULL,
  `reception`      TINYINT UNSIGNED NULL,
  `environment`    TINYINT UNSIGNED NULL,
  `consult_time`   TINYINT UNSIGNED NULL,
  `professional_ref` VARCHAR(180) NULL,
  `observation`    TEXT NULL,
  `answered_at`    DATETIME NULL,
  `created_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_surveys_public` (`public_id`),
  KEY `ix_surveys_tenant` (`tenant_id`),
  KEY `ix_surveys_appt` (`appointment_id`),
  CONSTRAINT `fk_surveys_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_surveys_appt`    FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_surveys_patient` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_survey_nps` CHECK (`nps` BETWEEN 0 AND 10),
  CONSTRAINT `ck_survey_punctuality` CHECK (`punctuality` BETWEEN 0 AND 10),
  CONSTRAINT `ck_survey_reception` CHECK (`reception` BETWEEN 0 AND 10),
  CONSTRAINT `ck_survey_environment` CHECK (`environment` BETWEEN 0 AND 10),
  CONSTRAINT `ck_survey_consult_time` CHECK (`consult_time` BETWEEN 0 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_id` BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'authorized',
  `description_snapshot` VARCHAR(200) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_exam_order_items_tenant` (`tenant_id`),
  CONSTRAINT `fk_exam_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_order_items_0` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_order_items_1` FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_exam_order_items_0` CHECK (`quantity` > 0 AND `unit_price` >= 0 AND `discount_amount` >= 0 AND `discount_amount` <= `quantity` * `unit_price`),
  UNIQUE KEY `uq_exam_order_items_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `exam_order_id` BIGINT UNSIGNED NULL,
  `unit_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `checked_in_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `source` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_visits_tenant` (`tenant_id`),
  UNIQUE KEY `uq_visits_0` (`public_id`),
  CONSTRAINT `fk_visits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_1` FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_2` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_3` FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_4` FOREIGN KEY (`tenant_id`,`created_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_visits_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `occupational_protocols` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `program_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(60) NOT NULL,
  `version` SMALLINT UNSIGNED NOT NULL,
  `valid_from` DATE NULL,
  `valid_until` DATE NULL,
  `rules` JSON NOT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_occupational_protocols_tenant` (`tenant_id`),
  UNIQUE KEY `uq_occupational_protocols_0` (`tenant_id`,`code`,`version`),
  CONSTRAINT `fk_occupational_protocols_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_protocols_0` FOREIGN KEY (`tenant_id`,`program_id`) REFERENCES `occupational_programs` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_protocols_1` FOREIGN KEY (`tenant_id`,`approved_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_occupational_protocols_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinical_receivables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `payer_type` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `settled_at` DATETIME NULL,
  `external_ref` VARCHAR(120) NULL,
  `idempotency_key` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_clinical_receivables_tenant` (`tenant_id`),
  UNIQUE KEY `uq_clinical_receivables_0` (`tenant_id`,`idempotency_key`),
  CONSTRAINT `fk_clinical_receivables_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_clinical_receivables_0` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_clinical_receivables_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_clinical_receivables_0` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `health_answers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `health_record_id` BIGINT UNSIGNED NOT NULL,
  `question_id`      BIGINT UNSIGNED NOT NULL,
  `answer_encrypted` MEDIUMTEXT NULL,
  `question_label_snapshot` TEXT NOT NULL,
  `question_type_snapshot` VARCHAR(20) NOT NULL,
  `question_options_snapshot` JSON NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ha_record_question` (`health_record_id`,`question_id`),
  KEY `ix_ha_tenant` (`tenant_id`),
  KEY `ix_ha_question` (`question_id`),
  CONSTRAINT `fk_ha_tenant`   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ha_record`   FOREIGN KEY (`tenant_id`,`health_record_id`) REFERENCES `health_records` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ha_question` FOREIGN KEY (`tenant_id`,`question_id`) REFERENCES `health_questions` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `visit_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_item_id` BIGINT UNSIGNED NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `room_id` BIGINT UNSIGNED NULL,
  `performed_by` BIGINT UNSIGNED NULL,
  `attempt_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `reason_encrypted` TEXT NULL,
  `source` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_exam_executions_tenant` (`tenant_id`),
  UNIQUE KEY `uq_exam_executions_0` (`tenant_id`,`visit_id`,`exam_type_id`,`attempt_no`),
  CONSTRAINT `fk_exam_executions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_0` FOREIGN KEY (`tenant_id`,`visit_id`) REFERENCES `visits` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_1` FOREIGN KEY (`tenant_id`,`exam_order_item_id`) REFERENCES `exam_order_items` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_2` FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_3` FOREIGN KEY (`tenant_id`,`room_id`) REFERENCES `rooms` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_4` FOREIGN KEY (`tenant_id`,`performed_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_exam_executions_0` CHECK (`attempt_no` > 0),
  CONSTRAINT `ck_exam_executions_1` CHECK (`finished_at` IS NULL OR `started_at` IS NULL OR `finished_at` >= `started_at`),
  UNIQUE KEY `uq_exam_executions_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `occupational_assessments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `employment_link_id` BIGINT UNSIGNED NOT NULL,
  `program_id` BIGINT UNSIGNED NULL,
  `protocol_id` BIGINT UNSIGNED NULL,
  `exam_order_id` BIGINT UNSIGNED NULL,
  `assessment_type` VARCHAR(40) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `assessed_on` DATE NULL,
  `assessed_by` BIGINT UNSIGNED NULL,
  `conclusion` VARCHAR(20) NULL,
  `context_snapshot_encrypted` MEDIUMTEXT NOT NULL,
  `protocol_snapshot_encrypted` MEDIUMTEXT NULL,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_occupational_assessments_tenant` (`tenant_id`),
  UNIQUE KEY `uq_occupational_assessments_0` (`public_id`),
  CONSTRAINT `fk_occupational_assessments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_1` FOREIGN KEY (`tenant_id`,`employment_link_id`) REFERENCES `patient_company_links` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_2` FOREIGN KEY (`tenant_id`,`program_id`) REFERENCES `occupational_programs` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_3` FOREIGN KEY (`tenant_id`,`protocol_id`) REFERENCES `occupational_protocols` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_4` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occupational_assessments_5` FOREIGN KEY (`tenant_id`,`assessed_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_occupational_assessments_0` CHECK (`assessment_type` IN ('admission','periodic','return_to_work','risk_change','dismissal')),
  CONSTRAINT `ck_occupational_assessments_1` CHECK (`conclusion` IS NULL OR `conclusion` IN ('fit','unfit')),
  CONSTRAINT `ck_occupational_assessments_2` CHECK (`status` <> 'completed' OR (`assessed_by` IS NOT NULL AND `assessed_on` IS NOT NULL AND `conclusion` IS NOT NULL AND `completed_at` IS NOT NULL)),
  UNIQUE KEY `uq_occupational_assessments_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_results` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `public_id`      CHAR(26) NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `patient_id`     BIGINT UNSIGNED NOT NULL,
  `exam_type_id`   BIGINT UNSIGNED NULL,
  `exam_execution_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `scope`          VARCHAR(20) NOT NULL DEFAULT 'exame',
  `framingham`     INT NULL,
  `exam_date`      DATE NULL,
  `released_at`    DATETIME NULL,
  `legacy_id`      BIGINT UNSIGNED NULL,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  `deleted_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_public` (`public_id`),
  KEY `ix_result_tenant_patient` (`tenant_id`,`patient_id`,`exam_date`),
  KEY `ix_result_appt` (`appointment_id`),
  KEY `ix_result_legacy` (`tenant_id`,`legacy_id`),
  CONSTRAINT `fk_result_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_appt`    FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_patient` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_exam`    FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_execution` FOREIGN KEY (`tenant_id`,`exam_execution_id`) REFERENCES `exam_executions` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_result_reviewer` FOREIGN KEY (`tenant_id`,`reviewed_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_exam_results_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_measurements` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`          BIGINT UNSIGNED NOT NULL,
  `exam_result_id`     BIGINT UNSIGNED NOT NULL,
  `measurement_type_id` BIGINT UNSIGNED NOT NULL,
  `value_numeric`      DECIMAL(12,3) NULL,
  `value_text`         VARCHAR(255) NULL,
  `flag`               VARCHAR(20) NOT NULL DEFAULT 'uninterpreted',
  `unit_snapshot` VARCHAR(20) NULL,
  `reference_snapshot` JSON NULL,
  `method_snapshot` VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_measure` (`exam_result_id`,`measurement_type_id`),
  KEY `ix_measure_tenant` (`tenant_id`),
  KEY `ix_measure_type` (`measurement_type_id`),
  CONSTRAINT `fk_measure_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_measure_result` FOREIGN KEY (`tenant_id`,`exam_result_id`) REFERENCES `exam_results` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_measure_type`   FOREIGN KEY (`tenant_id`,`measurement_type_id`) REFERENCES `measurement_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_measure_single_value` CHECK (`value_numeric` IS NULL OR `value_text` IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_conclusions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `exam_result_id` BIGINT UNSIGNED NOT NULL,
  `kind`           VARCHAR(40) NOT NULL,
  `body_encrypted` MEDIUMTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_concl_tenant` (`tenant_id`),
  KEY `ix_concl_result` (`exam_result_id`,`kind`),
  CONSTRAINT `fk_concl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_concl_result` FOREIGN KEY (`tenant_id`,`exam_result_id`) REFERENCES `exam_results` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       BIGINT UNSIGNED NOT NULL,
  `public_id`       CHAR(26) NOT NULL,
  `document_group` CHAR(26) NOT NULL,
  `version_no` INT UNSIGNED NOT NULL DEFAULT 1,
  `replaces_document_id` BIGINT UNSIGNED NULL,
  `amendment_reason` TEXT NULL,
  `signature_evidence_encrypted` MEDIUMTEXT NULL,
  `retention_until` DATE NULL,
  `legal_hold` TINYINT(1) NOT NULL DEFAULT 0,
  `patient_id`      BIGINT UNSIGNED NOT NULL,
  `appointment_id`  BIGINT UNSIGNED NULL,
  `exam_result_id`  BIGINT UNSIGNED NULL,
  `company_id`      BIGINT UNSIGNED NULL,
  `kind`            VARCHAR(30) NOT NULL DEFAULT 'laudo',
  `title`           VARCHAR(200) NOT NULL,
  `disk`            VARCHAR(40) NOT NULL DEFAULT 'tenant',
  `path`            VARCHAR(512) NOT NULL,
  `mime`            VARCHAR(100) NULL,
  `size_bytes`      BIGINT UNSIGNED NULL,
  `checksum_sha256` CHAR(64) NOT NULL,
  `status`          VARCHAR(20) NOT NULL DEFAULT 'draft',
  `signed_at`       DATETIME NULL,
  `signed_by`       BIGINT UNSIGNED NULL,
  `signature_provider` VARCHAR(30) NULL,
  `signature_ref`   VARCHAR(190) NULL,
  `released_at`     DATETIME NULL,
  `legacy_path`     VARCHAR(512) NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  `deleted_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documents_public` (`public_id`),
  KEY `ix_documents_patient_checksum` (`patient_id`,`checksum_sha256`),
  KEY `ix_documents_tenant_kind` (`tenant_id`,`kind`),
  KEY `ix_documents_appt` (`appointment_id`),
  KEY `ix_documents_result` (`exam_result_id`),
  CONSTRAINT `fk_documents_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_patient` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_company` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_appt`    FOREIGN KEY (`tenant_id`,`appointment_id`) REFERENCES `appointments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_result`  FOREIGN KEY (`tenant_id`,`exam_result_id`) REFERENCES `exam_results` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_documents_signer`  FOREIGN KEY (`tenant_id`,`signed_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_document_version` (`tenant_id`,`document_group`,`version_no`),
  CONSTRAINT `fk_document_previous` FOREIGN KEY (`tenant_id`,`replaces_document_id`) REFERENCES `documents` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_documents_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_accesses` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `accessor_type` VARCHAR(20) NOT NULL,
  `accessor_id` BIGINT UNSIGNED NULL,
  `action`      VARCHAR(20) NOT NULL,
  `ip`          VARCHAR(45) NULL,
  `created_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_docacc_tenant` (`tenant_id`),
  KEY `ix_docacc_document` (`document_id`,`created_at`),
  CONSTRAINT `fk_docacc_tenant`   FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_docacc_document` FOREIGN KEY (`tenant_id`,`document_id`) REFERENCES `documents` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aso_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `assessment_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `version_no` INT UNSIGNED NOT NULL,
  `issued_by` BIGINT UNSIGNED NOT NULL,
  `issued_at` DATETIME NOT NULL,
  `snapshot_encrypted` MEDIUMTEXT NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_aso_records_tenant` (`tenant_id`),
  UNIQUE KEY `uq_aso_records_0` (`tenant_id`,`assessment_id`,`version_no`),
  UNIQUE KEY `uq_aso_records_1` (`document_id`),
  CONSTRAINT `fk_aso_records_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_aso_records_0` FOREIGN KEY (`tenant_id`,`assessment_id`) REFERENCES `occupational_assessments` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_aso_records_1` FOREIGN KEY (`tenant_id`,`document_id`) REFERENCES `documents` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_aso_records_2` FOREIGN KEY (`tenant_id`,`issued_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_aso_records_0` CHECK (`version_no` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_deliveries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `recipient_type` VARCHAR(30) NOT NULL,
  `recipient_ref` VARCHAR(120) NOT NULL,
  `channel` VARCHAR(30) NOT NULL,
  `made_available_at` DATETIME NOT NULL,
  `acknowledged_at` DATETIME NULL,
  `evidence_encrypted` MEDIUMTEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_document_deliveries_tenant` (`tenant_id`),
  CONSTRAINT `fk_document_deliveries_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_document_deliveries_0` FOREIGN KEY (`tenant_id`,`document_id`) REFERENCES `documents` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `download_grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `recipient_type` VARCHAR(30) NOT NULL,
  `recipient_id` BIGINT UNSIGNED NULL,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME NULL,
  `issued_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_download_grants_tenant` (`tenant_id`),
  UNIQUE KEY `uq_download_grants_0` (`token_hash`),
  CONSTRAINT `fk_download_grants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_download_grants_0` FOREIGN KEY (`tenant_id`,`document_id`) REFERENCES `documents` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_download_grants_1` FOREIGN KEY (`tenant_id`,`issued_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
