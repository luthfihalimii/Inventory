<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrow_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('sop_document_id')->constrained()->restrictOnDelete();
            $table->timestampTz('accepted_at');
            $table->string('ip_address', 45)->nullable();

            $table->unique(['borrow_request_id', 'sop_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_acknowledgements');
    }
};
