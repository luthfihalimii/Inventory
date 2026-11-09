<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('asset_code')->unique();
            $table->string('serial_number')->nullable()->unique();
            $table->string('qr_token', 64)->unique();
            $table->enum('condition', ['good', 'damaged'])->default('good');
            $table->enum('status', ['available', 'borrowed', 'damaged', 'maintenance', 'lost', 'inactive'])
                ->default('available');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
