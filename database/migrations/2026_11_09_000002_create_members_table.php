<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code')->unique();
            $table->string('name');
            $table->string('nrp', 30)->unique();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('generation')->nullable();
            $table->string('position')->nullable();
            $table->text('notes')->nullable();
            $table->enum('membership_status', ['active', 'inactive'])->default('active');
            $table->enum('borrowing_status', ['eligible', 'borrowing_blocked'])->default('eligible');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
