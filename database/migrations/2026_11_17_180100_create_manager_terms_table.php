<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            // Generated key enforces at most one active account per manager role.
            $table->unsignedBigInteger('active_role_id')->nullable()
                ->storedAs('CASE WHEN ended_at IS NULL THEN role_id ELSE NULL END');
            $table->unique('active_role_id', 'manager_terms_one_active_account_per_role');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'role_id', 'ended_at'], 'manager_terms_active_lookup');
        });

        $managers = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.nama', ['ADMIN', 'SUPER_ADMIN'])
            ->whereNotNull('users.member_id')
            ->join('members', 'members.id', '=', 'users.member_id')
            ->select('users.id as user_id', 'users.member_id', 'users.created_at', 'users.status as account_status', 'members.status as member_status', 'roles.id as role_id')
            ->get();

        foreach ($managers as $manager) {
            DB::table('manager_terms')->insert([
                'user_id' => $manager->user_id,
                'member_id' => $manager->member_id,
                'role_id' => $manager->role_id,
                'started_at' => $manager->created_at ?? now(),
                'ended_at' => ($manager->account_status === 'ACTIVE' && $manager->member_status === 'ACTIVE') ? null : now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_terms');
    }
};
