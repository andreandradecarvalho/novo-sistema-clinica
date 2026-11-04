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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
    }
};
