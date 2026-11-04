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
CREATE TABLE `exam_type_preparations` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`    BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `title`        VARCHAR(150) NOT NULL,
  `instructions` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_prep_tenant` (`tenant_id`),
  KEY `ix_prep_exam` (`exam_type_id`),
  CONSTRAINT `fk_prep_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prep_exam`   FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_type_preparations');
    }
};
