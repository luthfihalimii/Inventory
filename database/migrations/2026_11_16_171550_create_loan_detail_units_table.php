<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_detail_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_detail_id')->constrained('loan_details')->cascadeOnDelete();
            $table->foreignId('inventory_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->enum('status', ['ACTIVE', 'REALLOCATED', 'RELEASED', 'COMPLETED'])->default('ACTIVE');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->unique(['loan_detail_id', 'inventory_unit_id']);
            $table->index(['inventory_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_detail_units');
    }
};
