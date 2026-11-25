<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_checklist_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_record_id')->constrained('return_records')->cascadeOnDelete();
            $table->string('nama_pemeriksaan');
            $table->boolean('is_checked')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_checklist_results');
    }
};
