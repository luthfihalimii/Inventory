<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreign('loan_transaction_id')->references('id')->on('loan_transactions')->restrictOnDelete();
            $table->foreign('loan_item_id')->references('id')->on('loan_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropForeign(['loan_transaction_id']);
            $table->dropForeign(['loan_item_id']);
        });
    }
};
