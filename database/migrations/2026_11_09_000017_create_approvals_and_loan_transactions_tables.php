<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_member_id')->constrained('members')->restrictOnDelete();
            $table->enum('approver_role', ['admin_korlab', 'wm']);
            $table->enum('decision', ['approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->timestampTz('decided_at');
            $table->timestampsTz();
        });

        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['reserved', 'borrowed', 'overdue', 'return_pending_verification', 'completed', 'cancelled'])
                ->default('reserved');
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('overdue_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'due_at']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_transactions');
        Schema::dropIfExists('approvals');
    }
};
