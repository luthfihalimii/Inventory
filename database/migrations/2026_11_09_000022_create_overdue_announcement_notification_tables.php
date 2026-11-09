<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_borrowing_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_transaction_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('member_id');
        });

        // One active block per loan: the scheduler must be idempotent.
        DB::statement('CREATE UNIQUE INDEX member_blocks_active_loan_idx ON member_borrowing_blocks (loan_transaction_id) WHERE ended_at IS NULL');
        DB::statement('CREATE INDEX member_blocks_member_active_idx ON member_borrowing_blocks (member_id) WHERE ended_at IS NULL');

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('member_borrowing_blocks');
    }
};
