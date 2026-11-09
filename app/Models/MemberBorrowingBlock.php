<?php

namespace App\Models;

use Database\Factories\MemberBorrowingBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property int $loan_transaction_id
 * @property string $reason
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $notes
 */
#[Fillable([
    'member_id',
    'loan_transaction_id',
    'reason',
    'started_at',
    'ended_at',
    'notes',
])]
class MemberBorrowingBlock extends Model
{
    /** @use HasFactory<MemberBorrowingBlockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<LoanTransaction, $this> */
    public function loanTransaction(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class);
    }
}
