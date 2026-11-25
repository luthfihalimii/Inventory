<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->date('tanggal_pemeliharaan');
            $table->text('deskripsi');
            $table->decimal('biaya', 15, 2)->default(0);
            $table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('SCHEDULED');
            $table->enum('kondisi_setelah', ['GOOD', 'MINOR_DAMAGE', 'DAMAGED'])->nullable();
            $table->timestamps();

            $table->index(['inventory_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
