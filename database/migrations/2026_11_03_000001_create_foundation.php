<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Foundation subset of docs/schema/schema.sql; clinical tables belong to later stages.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `permissions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(125) NOT NULL,
  `guard_name`  VARCHAR(125) NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `model_type`    VARCHAR(190) NOT NULL,
  `model_id`      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`,`tenant_id`),
  KEY `ix_mhp_model` (`model_id`,`model_type`),
  KEY `ix_mhp_tenant` (`tenant_id`),
  CONSTRAINT `fk_mhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `model_has_roles` (
  `role_id`    BIGINT UNSIGNED NOT NULL,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,                 -- team key
  `model_type` VARCHAR(190) NOT NULL,
  `model_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`,`tenant_id`),
  KEY `ix_mhr_model` (`model_id`,`model_type`),
  KEY `ix_mhr_tenant` (`tenant_id`),
  CONSTRAINT `fk_mhr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  CONSTRAINT `fk_rhp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rhp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('units');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('audit_log_central');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('platform_users');
    }
};
