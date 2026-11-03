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
CREATE TABLE `company_user_access` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `scope` VARCHAR(30) NOT NULL DEFAULT 'occupational_status',
  `valid_from` DATETIME NOT NULL,
  `valid_until` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `ix_company_user_access_tenant` (`tenant_id`),
  CONSTRAINT `fk_company_user_access_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_company_user_access_0` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_company_user_access_1` FOREIGN KEY (`tenant_id`,`company_id`) REFERENCES `companies` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_company_user_access_0` CHECK (`valid_until` IS NULL OR `valid_until` >= `valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user_access');
    }
};
