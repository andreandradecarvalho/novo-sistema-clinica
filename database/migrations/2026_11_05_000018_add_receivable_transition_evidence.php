<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `clinical_receivables` ADD COLUMN `transition_encrypted` TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `clinical_receivables` DROP COLUMN `transition_encrypted`');
    }
};
