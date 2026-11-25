<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasColumn('return_unit_inspections', 'pemeriksaan_ke')) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->unsignedSmallInteger('pemeriksaan_ke')->default(1)->after('inventory_unit_id');
            });
        }

        if (! $this->indexExists('return_unit_inspections', 'rui_record_unit_round_unique')) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->unique(
                    ['return_record_id', 'inventory_unit_id', 'pemeriksaan_ke'],
                    'rui_record_unit_round_unique'
                );
            });
        }

        if ($this->indexExists('return_unit_inspections', 'rui_record_unit_unique')) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->dropUnique('rui_record_unit_unique');
            });
        }

        if (! Schema::hasColumn('return_checklist_results', 'pemeriksaan_ke')) {
            Schema::table('return_checklist_results', function (Blueprint $table) {
                $table->unsignedSmallInteger('pemeriksaan_ke')->default(1)->after('return_record_id');
            });
        }

        if (! $this->indexExists('return_checklist_results', 'rcr_record_round_index')) {
            Schema::table('return_checklist_results', function (Blueprint $table) {
                $table->index(['return_record_id', 'pemeriksaan_ke'], 'rcr_record_round_index');
            });
        }
    }

    public function down(): void
    {
        $hasRepeatedRounds = DB::table('return_unit_inspections')
            ->select('return_record_id', 'inventory_unit_id')
            ->groupBy('return_record_id', 'inventory_unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedRounds) {
            throw new RuntimeException(
                'Cannot roll back inspection rounds: repeated inspection history exists.'
            );
        }

        if ($this->indexExists('return_unit_inspections', 'rui_record_unit_unique') === false) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->unique(['return_record_id', 'inventory_unit_id'], 'rui_record_unit_unique');
            });
        }

        if ($this->indexExists('return_unit_inspections', 'rui_record_unit_round_unique')) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->dropUnique('rui_record_unit_round_unique');
            });
        }

        if ($this->indexExists('return_checklist_results', 'rcr_record_round_index')) {
            Schema::table('return_checklist_results', function (Blueprint $table) {
                $table->dropIndex('rcr_record_round_index');
            });
        }

        if (Schema::hasColumn('return_checklist_results', 'pemeriksaan_ke')) {
            Schema::table('return_checklist_results', function (Blueprint $table) {
                $table->dropColumn('pemeriksaan_ke');
            });
        }

        if (Schema::hasColumn('return_unit_inspections', 'pemeriksaan_ke')) {
            Schema::table('return_unit_inspections', function (Blueprint $table) {
                $table->dropColumn('pemeriksaan_ke');
            });
        }
    }
};
