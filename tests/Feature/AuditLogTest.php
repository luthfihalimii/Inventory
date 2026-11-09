<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded(): void
    {
        $user = User::factory()->create(['username' => 'someone']);
        $user->roles()->attach(Role::factory()->create(['name' => 'user']));

        $this->post(route('login.store'), [
            'username' => 'someone',
            'password' => 'password',
        ]);

        $log = AuditLog::where('action', 'auth.login')->firstOrFail();

        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());

        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame($user->member_id, $log->actor_member_id);
        $this->assertSame('user', $log->actor_role);
    }

    public function test_failed_login_is_recorded_without_actor(): void
    {
        $this->post(route('login.store'), [
            'username' => 'ghost',
            'password' => 'password',
        ]);

        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();

        $this->assertSame(1, AuditLog::where('action', 'auth.login_failed')->count());

        $this->assertNull($log->actor_user_id);
        $this->assertSame('ghost', $log->metadata['username']);
    }

    public function test_logger_records_actor_and_subject(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actor->roles()->attach(Role::factory()->create(['name' => 'wm']));
        $this->actingAs($actor);

        app(AuditLogger::class)->record('account.updated', $target, ['status' => 'inactive']);

        $log = AuditLog::where('action', 'account.updated')->firstOrFail();

        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame('wm', $log->actor_role);
        $this->assertSame(User::class, $log->entity_type);
        $this->assertSame($target->id, $log->entity_id);
        $this->assertSame('inactive', $log->metadata['status']);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLog::create([
            'action' => 'test',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = AuditLog::create([
            'action' => 'test',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_audit_log_cannot_be_mass_deleted(): void
    {
        AuditLog::create(['action' => 'test', 'created_at' => now()]);

        $this->expectException(QueryException::class);

        AuditLog::query()->delete();
    }

    public function test_audit_log_cannot_be_quietly_updated(): void
    {
        $log = AuditLog::create(['action' => 'test', 'created_at' => now()]);

        $this->expectException(QueryException::class);

        $log->forceFill(['action' => 'tampered'])->saveQuietly();
    }

    public function test_audit_log_cannot_be_quietly_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test', 'created_at' => now()]);

        $this->expectException(QueryException::class);

        $log->deleteQuietly();
    }
}
