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
CREATE TABLE `professionals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `council`     VARCHAR(10) NULL,
  `council_uf`  CHAR(2) NULL,
  `council_number` VARCHAR(20) NULL,
  `specialty`   VARCHAR(60) NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professionals_user` (`user_id`),
  KEY `ix_professionals_tenant` (`tenant_id`),
  CONSTRAINT `fk_professionals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_professionals_user`   FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  UNIQUE KEY `uq_professionals_tenant_id` (`tenant_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
