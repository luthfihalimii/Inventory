<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_returned')->default(0)->after('quantity');
        });

        // Loans closed before partial returns existed were always returned in full.
        DB::table('loan_items')
            ->whereIn('loan_transaction_id', DB::table('loan_transactions')->where('status', 'completed')->select('id'))
            ->update(['quantity_returned' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });
    }
};
