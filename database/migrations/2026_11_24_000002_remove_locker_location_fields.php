<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lokasi_loker_id');
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locker_location_id');
        });

        Schema::dropIfExists('locker_locations');
    }

    public function down(): void
    {
        Schema::create('locker_locations', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->foreignId('lokasi_loker_id')->nullable()->constrained('locker_locations')->nullOnDelete();
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('locker_location_id')->nullable()->constrained('locker_locations')->nullOnDelete();
        });
    }
};
