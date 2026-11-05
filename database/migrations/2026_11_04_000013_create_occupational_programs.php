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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('occupational_programs');
    }
};
