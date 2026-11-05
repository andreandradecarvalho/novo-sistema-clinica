<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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
  `context_encrypted` TEXT NULL,
  `creation_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `creation_request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  UNIQUE KEY `uq_appt_creation` (`tenant_id`,`creation_key_hash`),
  CONSTRAINT `ck_appt_creation_pair` CHECK ((`creation_key_hash` IS NULL AND `creation_request_hash` IS NULL) OR (`creation_key_hash` IS NOT NULL AND `creation_request_hash` IS NOT NULL)),
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
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `appointments`');
    }
};
