<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

class LogAuthentication
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        AuditLog::create([
            'actor_user_id' => $user->id,
            'actor_member_id' => $user->member_id,
            'actor_role' => $user->roles()->value('name'),
            'action' => 'auth.login',
            'entity_type' => $user::class,
            'entity_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        AuditLog::create([
            'action' => 'auth.login_failed',
            'metadata' => ['username' => $event->credentials['username'] ?? null],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
