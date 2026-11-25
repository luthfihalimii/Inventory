<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('announcements', 'kategori')) {
            Schema::table('announcements', fn (Blueprint $table) => $table->string('kategori', 30)->default('INFORMASI'));
        }
        if (! Schema::hasColumn('announcements', 'is_pinned')) {
            Schema::table('announcements', fn (Blueprint $table) => $table->boolean('is_pinned')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('announcements', 'is_pinned')) Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn('is_pinned'));
        if (Schema::hasColumn('announcements', 'kategori')) Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn('kategori'));
    }
};
