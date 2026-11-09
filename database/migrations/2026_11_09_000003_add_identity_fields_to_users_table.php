<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->after('id')->constrained()->restrictOnDelete();
            $table->string('username', 50)->unique()->after('member_id');
            $table->string('email')->nullable()->change();
            $table->enum('status', ['active', 'inactive'])->default('active')->after('password');
            $table->boolean('must_change_password')->default(false);
            $table->timestampTz('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'status', 'must_change_password', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
