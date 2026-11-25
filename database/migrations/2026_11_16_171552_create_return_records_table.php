<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_request_id')
                ->unique()
                ->constrained('loan_requests')
                ->restrictOnDelete();

            $table->foreignId('diajukan_oleh')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('diverifikasi_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('waktu_pengajuan');
            $table->dateTime('waktu_verifikasi')->nullable();

            $table->enum('status', [
                'SUBMITTED',
                'VERIFIED',
                'ISSUE_FOUND',
            ])->default('SUBMITTED');

            $table->text('catatan_pengguna')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_records');
    }
};
