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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_company_links');
    }
};
