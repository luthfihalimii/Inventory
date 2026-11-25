
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('kode', 50)->nullable()->change();
            $table->string('nrp', 30)->nullable()->change();

            $table->foreignId('divisi_id')->nullable()->change();
            $table->foreignId('jabatan_id')->nullable()->change();
            $table->foreignId('generasi_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak dapat dikembalikan jika masih ada data NULL.
        Schema::table('members', function (Blueprint $table) {
            $table->string('kode', 50)->nullable(false)->change();
            $table->string('nrp', 30)->nullable(false)->change();

            $table->foreignId('divisi_id')->nullable(false)->change();
            $table->foreignId('jabatan_id')->nullable(false)->change();
            $table->foreignId('generasi_id')->nullable(false)->change();
        });
    }
};
