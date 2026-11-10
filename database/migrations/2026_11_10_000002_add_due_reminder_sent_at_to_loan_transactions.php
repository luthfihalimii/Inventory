<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            $table->timestampTz('due_reminder_sent_at')->nullable()->after('overdue_at');
        });
    }

    public function down(): void
    {
        Schema::table('loan_transactions', function (Blueprint $table) {
            $table->dropColumn('due_reminder_sent_at');
        });
    }
};
