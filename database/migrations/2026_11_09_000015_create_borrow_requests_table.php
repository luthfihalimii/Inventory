<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->nullable()->unique();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('category', ['scheduled', 'urgent']);
            $table->timestampTz('pickup_at');
            $table->timestampTz('return_at');
            $table->string('usage_location')->nullable();
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'cancelled'])->default('pending_approval');
            $table->timestampTz('submitted_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'category', 'submitted_at']);
        });

        Schema::create('borrow_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['borrow_request_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_request_items');
        Schema::dropIfExists('borrow_requests');
    }
};
