<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_unit_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_record_id')->constrained('return_records')->cascadeOnDelete();
            $table->foreignId('inventory_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->enum('kondisi_ditemukan', ['GOOD', 'MINOR_DAMAGE', 'DAMAGED']);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(
                ['return_record_id', 'inventory_unit_id'],
                'rui_record_unit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_unit_inspections');
    }
};
