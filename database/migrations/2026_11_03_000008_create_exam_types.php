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
CREATE TABLE `exam_types` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     BIGINT UNSIGNED NOT NULL,
  `public_id`     CHAR(26) NOT NULL,
  `code`          VARCHAR(20) NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `scope`         VARCHAR(20) NOT NULL DEFAULT 'exame',
  `requires_fasting` TINYINT(1) NOT NULL DEFAULT 0,
  `default_duration_min` SMALLINT UNSIGNED NULL,
  `price`         DECIMAL(12,2) NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_types_public` (`public_id`),
  UNIQUE KEY `uq_exam_types_tenant_code` (`tenant_id`,`code`),
  KEY `ix_exam_types_tenant` (`tenant_id`),
  CONSTRAINT `fk_exam_types_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_exam_types_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_types');
    }
};
