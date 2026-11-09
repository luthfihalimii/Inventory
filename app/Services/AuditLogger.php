<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Append an audit entry. Never throws for missing actor or request context.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'actor_user_id' => $user?->id,
            'actor_member_id' => $user?->member_id,
            'actor_role' => $user?->roles->first()?->name,
            'action' => $action,
            'entity_type' => $subject ? $subject::class : null,
            'entity_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
