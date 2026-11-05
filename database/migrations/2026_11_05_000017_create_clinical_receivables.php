<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `clinical_receivables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `payer_type` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `settled_at` DATETIME NULL,
  `external_ref` VARCHAR(120) NULL,
  `idempotency_key` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_clinical_receivables_tenant` (`tenant_id`),
  UNIQUE KEY `uq_clinical_receivables_0` (`tenant_id`,`idempotency_key`),
  CONSTRAINT `fk_clinical_receivables_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_clinical_receivables_0` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_clinical_receivables_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_clinical_receivables_0` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `clinical_receivables`');
    }
};
