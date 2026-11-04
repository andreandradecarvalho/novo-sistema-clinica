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
CREATE TABLE `exam_type_attachments` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `label`        VARCHAR(150) NOT NULL,
  `disk`         VARCHAR(40) NOT NULL DEFAULT 'tenant',
  `path`         VARCHAR(512) NOT NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_examatt_tenant` (`tenant_id`),
  KEY `ix_examatt_exam` (`exam_type_id`),
  CONSTRAINT `fk_examatt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_examatt_exam`   FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_type_attachments');
    }
};
