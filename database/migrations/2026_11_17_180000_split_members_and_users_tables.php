<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateManagerRole = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.nama', ['ADMIN', 'SUPER_ADMIN'])
            ->select('roles.nama')
            ->groupBy('roles.nama')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateManagerRole) {
            throw new RuntimeException(
                "Ditemukan lebih dari satu akun {$duplicateManagerRole->nama}. Tentukan akun pengelola yang dipertahankan sebelum migrasi pemisahan Members dan Users."
            );
        }

        $userWithoutRole = DB::table('users')
            ->leftJoin('role_user', 'role_user.user_id', '=', 'users.id')
            ->whereNull('role_user.user_id')
            ->value('users.id');

        if ($userWithoutRole) {
            throw new RuntimeException(
                "Akun users.id={$userWithoutRole} belum memiliki role. Tetapkan satu role sebelum menjalankan migrasi pemisahan Members dan Users."
            );
        }

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nrp', 30)->unique();
            $table->string('nama', 150);
            $table->foreignId('divisi_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('jabatan_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('generasi_id')->constrained('generations')->restrictOnDelete();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->after('id')->constrained('members')->restrictOnDelete();
            $table->string('username', 60)->nullable()->unique()->after('member_id');
        });

        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            $memberId = DB::table('members')->insertGetId([
                'kode' => $user->kode,
                'nrp' => $user->nrp,
                'nama' => $user->nama,
                'divisi_id' => $user->divisi_id,
                'jabatan_id' => $user->jabatan_id,
                'generasi_id' => $user->generasi_id,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);

            $isManager = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->whereIn('roles.nama', ['ADMIN', 'SUPER_ADMIN'])
                ->exists();

            DB::table('users')->where('id', $user->id)->update([
                'member_id' => $memberId,
                // Kode anggota lama dipakai sebagai username awal untuk akun pengelola.
                'username' => $isManager
                    ? (ctype_digit((string) $user->kode) ? 'manager-' . $user->kode : $user->kode)
                    : null,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['divisi_id']);
            $table->dropForeign(['jabatan_id']);
            $table->dropForeign(['generasi_id']);
            $table->dropUnique('users_kode_unique');
            $table->dropUnique('users_nrp_unique');
            $table->dropColumn([
                'kode',
                'nrp',
                'nama',
                'divisi_id',
                'jabatan_id',
                'generasi_id',
            ]);
        });
    }

    public function down(): void
    {
        // Tidak dapat dibalik otomatis: satu Member kini dapat memiliki banyak User.
        throw new RuntimeException(
            'Migrasi pemisahan Members dan Users tidak dapat di-rollback otomatis tanpa risiko duplikasi NRP. Pulihkan backup database jika perlu rollback.'
        );
    }
};
