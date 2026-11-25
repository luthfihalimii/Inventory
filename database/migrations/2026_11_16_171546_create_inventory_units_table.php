<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('kode_unit')->unique();
            $table->decimal('harga_pembelian', 15, 2)->nullable();
            $table->date('tanggal_pembelian')->nullable();
            $table->date('terakhir_dipelihara')->nullable();
            $table->date('jadwal_pemeliharaan_berikutnya')->nullable();
            $table->enum('kondisi', ['GOOD', 'MINOR_DAMAGE', 'DAMAGED'])->default('GOOD');
            $table->enum('status', ['AVAILABLE', 'RESERVED', 'BORROWED', 'MAINTENANCE'])->default('AVAILABLE');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
