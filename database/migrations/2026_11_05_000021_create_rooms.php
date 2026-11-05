<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `rooms` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED NOT NULL,
  `unit_id`    BIGINT UNSIGNED NULL,
  `room_key`   VARCHAR(20) NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `pin_hash`   VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_tenant_key` (`tenant_id`,`room_key`),
  KEY `ix_rooms_tenant` (`tenant_id`),
  KEY `ix_rooms_unit` (`unit_id`),
  CONSTRAINT `fk_rooms_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_rooms_unit`   FOREIGN KEY (`tenant_id`,`unit_id`) REFERENCES `units` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_rooms_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `rooms`');
    }
};
