<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `health_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `public_id`   CHAR(26) NOT NULL,
  `patient_id`  BIGINT UNSIGNED NOT NULL,
  `appointment_id` BIGINT UNSIGNED NULL,
  `visit_id` BIGINT UNSIGNED NULL,
  `context_encrypted` TEXT NULL,
  `creation_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  UNIQUE KEY `uq_hr_creation` (`tenant_id`,`creation_key_hash`),
  KEY `ix_hr_visit` (`tenant_id`,`visit_id`,`id`),
  CONSTRAINT `fk_hr_visit` FOREIGN KEY (`tenant_id`,`visit_id`) REFERENCES `visits` (`tenant_id`,`id`) ON DELETE RESTRICT,
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
SQL);
        DB::statement(<<<'SQL'
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
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `health_answers`');
        DB::statement('DROP TABLE `health_records`');
    }
};
