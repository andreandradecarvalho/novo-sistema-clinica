<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
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
  `context_encrypted` TEXT NULL,
  UNIQUE KEY `uq_visits_appointment` (`tenant_id`,`appointment_id`),
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
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `visits`');
    }
};
