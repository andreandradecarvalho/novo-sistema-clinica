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
CREATE TABLE `exam_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `patient_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `company_unit_id` BIGINT UNSIGNED NULL,
  `requested_by` BIGINT UNSIGNED NULL,
  `payer_type` VARCHAR(20) NOT NULL DEFAULT 'patient',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `pricing_snapshot` JSON NULL,
  `context_encrypted` MEDIUMTEXT NULL,
  `authorized_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_exam_orders_tenant` (`tenant_id`),
  UNIQUE KEY `uq_exam_orders_0` (`public_id`),
  CONSTRAINT `fk_exam_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_0` FOREIGN KEY (`tenant_id`,`patient_id`) REFERENCES `patients` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_2` FOREIGN KEY (`tenant_id`,`company_unit_id`) REFERENCES `company_units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_orders_3` FOREIGN KEY (`tenant_id`,`requested_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_exam_orders_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `exam_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_id` BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'authorized',
  `description_snapshot` VARCHAR(200) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_exam_order_items_tenant` (`tenant_id`),
  CONSTRAINT `fk_exam_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_order_items_0` FOREIGN KEY (`tenant_id`,`exam_order_id`) REFERENCES `exam_orders` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_order_items_1` FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_exam_order_items_0` CHECK (`quantity` > 0 AND `unit_price` >= 0 AND `discount_amount` >= 0 AND `discount_amount` <= `quantity` * `unit_price`),
  UNIQUE KEY `uq_exam_order_items_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_order_items');
        Schema::dropIfExists('exam_orders');
    }
};
