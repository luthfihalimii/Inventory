<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->enum('tracking_method', ['serialized', 'quantity']);
            $table->unsignedInteger('quantity_total')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->date('acquired_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('qr_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE items ADD CONSTRAINT items_quantity_tracking_check CHECK (
            (tracking_method = 'quantity' AND quantity_total IS NOT NULL)
            OR (tracking_method = 'serialized' AND quantity_total IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
