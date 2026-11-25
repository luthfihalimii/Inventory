<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
                            $table->id();
                            $table->string('kode')->unique();
                            $table->string('nrp')->unique();
                            $table->string('nama');
                            $table->string('password');
                            $table->foreignId('divisi_id')->constrained('divisions')->restrictOnDelete();
                            $table->foreignId('jabatan_id')->nullable()->constrained('positions')->nullOnDelete();
                            $table->foreignId('generasi_id')->constrained('generations')->restrictOnDelete();
                            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
                            $table->rememberToken();
                            $table->timestamps();
                        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
