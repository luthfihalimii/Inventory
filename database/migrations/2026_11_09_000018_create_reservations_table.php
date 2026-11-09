<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrow_request_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_fulfilled')->default(0);
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->enum('status', ['active', 'fulfilled', 'released'])->default('active');
            $table->timestampsTz();

            $table->index(['item_id', 'start_at', 'end_at', 'status']);
            $table->index('borrow_request_id');
        });

        // Serialized units may not be double-booked for overlapping periods.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(
            "ALTER TABLE reservations
             ADD CONSTRAINT reservations_asset_no_overlap
             EXCLUDE USING gist (
                 asset_id WITH =,
                 tstzrange(start_at, end_at, '[)') WITH &&
             )
             WHERE (asset_id IS NOT NULL AND status = 'active')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
