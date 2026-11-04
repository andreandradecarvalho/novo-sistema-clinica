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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_merges');
    }
};
