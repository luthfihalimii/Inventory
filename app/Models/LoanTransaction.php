<?php

namespace App\Models;

use Database\Factories\LoanTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $borrow_request_id
 * @property int $member_id
 * @property string $status
 * @property Carbon|null $checked_out_at
 * @property Carbon|null $due_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $overdue_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'borrow_request_id',
    'member_id',
    'status',
    'checked_out_at',
    'due_at',
    'returned_at',
    'completed_at',
    'overdue_at',
    'cancelled_at',
    'cancellation_reason',
])]
class LoanTransaction extends Model
{
    /** @use HasFactory<LoanTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'completed_at' => 'datetime',
            'overdue_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
