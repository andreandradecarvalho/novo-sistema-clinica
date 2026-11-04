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
CREATE TABLE `health_question_groups` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `code`      VARCHAR(40) NOT NULL,
  `name`      VARCHAR(120) NOT NULL,
  `ordem`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hqg_tenant_code` (`tenant_id`,`code`),
  KEY `ix_hqg_tenant` (`tenant_id`),
  CONSTRAINT `fk_hqg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_health_question_groups_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        DB::statement(<<<'SQL'
CREATE TABLE `health_questions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `group_id`   BIGINT UNSIGNED NOT NULL,
  `code`       VARCHAR(60) NOT NULL,
  `label`      VARCHAR(255) NOT NULL,
  `type`       VARCHAR(20) NOT NULL DEFAULT 'text',
  `options`    JSON NULL,
  `applies_to` VARCHAR(12) NOT NULL DEFAULT 'all',
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `version`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hq_tenant_code` (`tenant_id`,`code`,`version`),
  KEY `ix_hq_tenant` (`tenant_id`),
  KEY `ix_hq_group` (`group_id`),
  CONSTRAINT `fk_hq_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hq_group`  FOREIGN KEY (`tenant_id`,`group_id`) REFERENCES `health_question_groups` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_health_questions_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('health_questions');
        Schema::dropIfExists('health_question_groups');
    }
};
