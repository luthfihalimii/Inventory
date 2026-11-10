<?php

namespace App\Models;

use Database\Factories\LoanExtensionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_transaction_id
 * @property int $requested_by_user_id
 * @property Carbon $current_due_at
 * @property Carbon $requested_due_at
 * @property string $reason
 * @property string $status
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 */
#[Fillable([
    'loan_transaction_id',
    'requested_by_user_id',
    'current_due_at',
    'requested_due_at',
    'reason',
    'status',
    'decided_by_user_id',
    'decided_at',
    'decision_notes',
])]
class LoanExtensionRequest extends Model
{
    /** @use HasFactory<LoanExtensionRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_due_at' => 'datetime',
            'requested_due_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LoanTransaction, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class, 'loan_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
