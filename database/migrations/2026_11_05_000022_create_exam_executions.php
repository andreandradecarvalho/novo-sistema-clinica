<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `exam_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `visit_id` BIGINT UNSIGNED NOT NULL,
  `exam_order_item_id` BIGINT UNSIGNED NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `room_id` BIGINT UNSIGNED NULL,
  `performed_by` BIGINT UNSIGNED NULL,
  `attempt_no` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `reason_encrypted` TEXT NULL,
  `source` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `context_encrypted` TEXT NULL,
  `creation_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `creation_request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  UNIQUE KEY `uq_execution_creation` (`tenant_id`,`creation_key_hash`),
  CONSTRAINT `ck_execution_creation_pair` CHECK ((`creation_key_hash` IS NULL AND `creation_request_hash` IS NULL) OR (`creation_key_hash` IS NOT NULL AND `creation_request_hash` IS NOT NULL)),
  CONSTRAINT `fk_execution_creator` FOREIGN KEY (`tenant_id`,`created_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  PRIMARY KEY (`id`),
  KEY `ix_exam_executions_tenant` (`tenant_id`),
  UNIQUE KEY `uq_exam_executions_0` (`tenant_id`,`visit_id`,`exam_type_id`,`attempt_no`),
  CONSTRAINT `fk_exam_executions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_0` FOREIGN KEY (`tenant_id`,`visit_id`) REFERENCES `visits` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_1` FOREIGN KEY (`tenant_id`,`exam_order_item_id`) REFERENCES `exam_order_items` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_2` FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_3` FOREIGN KEY (`tenant_id`,`room_id`) REFERENCES `rooms` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_exam_executions_4` FOREIGN KEY (`tenant_id`,`performed_by`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_exam_executions_0` CHECK (`attempt_no` > 0),
  CONSTRAINT `ck_exam_executions_1` CHECK (`finished_at` IS NULL OR `started_at` IS NULL OR `finished_at` >= `started_at`),
  UNIQUE KEY `uq_exam_executions_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `exam_executions`');
    }
};
