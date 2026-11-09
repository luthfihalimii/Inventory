<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->enum('status', ['pending', 'verified', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'requested_at']);
        });

        DB::statement("CREATE UNIQUE INDEX return_requests_one_pending_idx ON return_requests (loan_transaction_id) WHERE status = 'pending'");

        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('officer_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('inspected_at');
            $table->text('notes')->nullable();
            $table->jsonb('photos')->nullable();
            $table->timestampsTz();
        });

        Schema::create('return_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_item_id')->constrained()->restrictOnDelete();
            $table->enum('condition', ['good', 'damaged', 'incomplete', 'lost']);
            $table->unsignedInteger('quantity_returned');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('loan_item_id');
        });

        DB::statement('ALTER TABLE return_inspection_items ADD CONSTRAINT return_inspection_items_quantity_check CHECK (quantity_returned > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_inspection_items');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_requests');
    }
};
