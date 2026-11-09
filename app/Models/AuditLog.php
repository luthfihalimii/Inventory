<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property int|null $actor_member_id
 * @property string|null $actor_role
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable([
    'actor_user_id',
    'actor_member_id',
    'actor_role',
    'action',
    'entity_type',
    'entity_id',
    'before',
    'after',
    'metadata',
    'ip_address',
    'user_agent',
    'created_at',
])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log is append-only.'));

        static::deleting(fn () => throw new LogicException('Audit log is append-only.'));
    }
}
