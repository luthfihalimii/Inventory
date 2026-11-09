<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            // Loan columns are plain integers until Sprint 5/6 add their foreign keys.
            $table->unsignedBigInteger('loan_transaction_id')->nullable();
            $table->unsignedBigInteger('loan_item_id')->nullable();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['damage', 'loss', 'incomplete', 'maintenance']);
            $table->unsignedInteger('quantity')->nullable();
            $table->text('description');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->enum('resolution', ['repaired', 'written_off'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('reported_at')->useCurrent();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['item_id', 'status']);
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
