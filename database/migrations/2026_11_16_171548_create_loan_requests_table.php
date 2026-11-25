<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pengajuan')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('kategori', ['URGENT', 'SCHEDULED']);
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai');
            $table->foreignId('lokasi_loker_id')->constrained('locker_locations')->restrictOnDelete();
            $table->text('catatan')->nullable();

            $table->enum('status', [
                'PENDING',
                'APPROVED',
                'NEEDS_ADJUSTMENT',
                'REJECTED',
                'BORROWED',
                'RETURN_PENDING',
                'RETURN_ISSUE',
                'COMPLETED',
                'CANCELLED',
            ])->default('PENDING');

            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('waktu_keputusan')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_requests');
    }
};
