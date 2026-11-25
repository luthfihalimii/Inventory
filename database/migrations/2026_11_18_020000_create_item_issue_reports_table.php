<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('item_issue_reports', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();$t->foreignId('inventory_unit_id')->nullable()->constrained()->nullOnDelete();$t->string('judul',160);$t->text('deskripsi');$t->enum('status',['OPEN','IN_PROGRESS','RESOLVED','REJECTED'])->default('OPEN');$t->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();$t->text('catatan_tindak_lanjut')->nullable();$t->timestamp('waktu_selesai')->nullable();$t->timestamps();$t->index(['status','created_at']);});} public function down(): void {Schema::dropIfExists('item_issue_reports');}};
