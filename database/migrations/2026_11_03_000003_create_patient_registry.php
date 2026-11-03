<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('company_units');
        Schema::dropIfExists('companies');
    }
};
