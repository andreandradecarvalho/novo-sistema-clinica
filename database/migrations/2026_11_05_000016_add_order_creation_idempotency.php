<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE `exam_orders`
  ADD COLUMN `creation_key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN `creation_request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD UNIQUE KEY `uq_exam_orders_creation` (`tenant_id`,`creation_key_hash`),
  ADD CONSTRAINT `ck_exam_orders_creation_pair` CHECK ((`creation_key_hash` IS NULL AND `creation_request_hash` IS NULL) OR (`creation_key_hash` IS NOT NULL AND `creation_request_hash` IS NOT NULL))
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE `exam_orders`
  DROP CONSTRAINT `ck_exam_orders_creation_pair`,
  DROP INDEX `uq_exam_orders_creation`,
  DROP COLUMN `creation_request_hash`,
  DROP COLUMN `creation_key_hash`
SQL);
    }
};
