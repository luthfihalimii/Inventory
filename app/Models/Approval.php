<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $borrow_request_id
 * @property int $approver_user_id
 * @property int $approver_member_id
 * @property string $approver_role
 * @property string $decision
 * @property string|null $notes
 * @property Carbon $decided_at
 */
#[Fillable([
    'borrow_request_id',
    'approver_user_id',
    'approver_member_id',
    'approver_role',
    'decision',
    'notes',
    'decided_at',
])]
class Approval extends Model
{
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function approverMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'approver_member_id');
    }
}
