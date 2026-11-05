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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('occupational_protocols');
    }
};
