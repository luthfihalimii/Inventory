<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_extension_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('current_due_at');
            $table->timestampTz('requested_due_at');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        // Only one open request per loan, enforced by the database.
        DB::statement('CREATE UNIQUE INDEX loan_extension_requests_pending_unique ON loan_extension_requests (loan_transaction_id) WHERE status = \'pending\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_extension_requests');
    }
};
