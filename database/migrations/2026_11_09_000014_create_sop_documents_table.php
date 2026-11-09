<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('version')->unique();
            $table->string('title');
            $table->longText('content');
            $table->timestampTz('effective_at');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX sop_documents_one_active_idx ON sop_documents (is_active) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_documents');
    }
};
