<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('condition_out', ['good', 'damaged'])->default('good');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('loan_transaction_id');
            $table->index('item_id');
            $table->index('asset_id');
        });

        DB::statement('ALTER TABLE loan_items ADD CONSTRAINT loan_items_serialized_quantity_check CHECK (asset_id IS NULL OR quantity = 1)');

        Schema::create('handover_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('officer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('borrower_member_id')->constrained('members')->restrictOnDelete();
            $table->timestampTz('handed_at');
            $table->jsonb('checklist')->nullable();
            $table->jsonb('scan_results')->nullable();
            $table->enum('borrower_confirmation_method', ['on_site', 'self'])->default('on_site');
            $table->timestampTz('borrower_confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_records');
        Schema::dropIfExists('loan_items');
    }
};
