<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "CREATE UNIQUE INDEX damage_reports_one_open_per_asset_idx
             ON damage_reports (asset_id)
             WHERE status = 'open' AND asset_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS damage_reports_one_open_per_asset_idx');
    }
};
