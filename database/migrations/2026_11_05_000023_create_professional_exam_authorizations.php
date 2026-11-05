<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `professional_exam_authorizations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `professional_id` BIGINT UNSIGNED NOT NULL,
  `exam_type_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NOT NULL,
  `granted_at` DATETIME NOT NULL,
  `grant_reason_encrypted` TEXT NOT NULL,
  `revoked_by` BIGINT UNSIGNED NULL,
  `revoked_at` DATETIME NULL,
  `revocation_reason_encrypted` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  `active_flag` TINYINT UNSIGNED GENERATED ALWAYS AS (IF(`revoked_at` IS NULL, 1, NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prof_exam_active` (`tenant_id`,`professional_id`,`exam_type_id`,`active_flag`),
  UNIQUE KEY `uq_prof_exam_tenant_id` (`tenant_id`,`id`),
  CONSTRAINT `fk_prof_exam_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prof_exam_professional` FOREIGN KEY (`tenant_id`,`professional_id`) REFERENCES `professionals` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prof_exam_exam` FOREIGN KEY (`tenant_id`,`exam_type_id`) REFERENCES `exam_types` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prof_exam_granter` FOREIGN KEY (`tenant_id`,`granted_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prof_exam_revoker` FOREIGN KEY (`tenant_id`,`revoked_by`) REFERENCES `users` (`tenant_id`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_prof_exam_revocation` CHECK ((`revoked_at` IS NULL AND `revoked_by` IS NULL AND `revocation_reason_encrypted` IS NULL) OR (`revoked_at` IS NOT NULL AND `revoked_by` IS NOT NULL AND `revocation_reason_encrypted` IS NOT NULL AND `revoked_at` >= `granted_at`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE `professional_exam_authorizations`');
    }
};
