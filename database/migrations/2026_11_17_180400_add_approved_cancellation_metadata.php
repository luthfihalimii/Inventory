<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->text('alasan_pembatalan')->nullable()->after('alasan_penolakan');
            $table->foreignId('dibatalkan_oleh')->nullable()->after('alasan_pembatalan')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('waktu_pembatalan')->nullable()->after('dibatalkan_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibatalkan_oleh');
            $table->dropColumn(['alasan_pembatalan', 'waktu_pembatalan']);
        });
    }
};
