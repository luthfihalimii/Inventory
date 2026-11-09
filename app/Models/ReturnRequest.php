<?php

namespace App\Models;

use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_transaction_id
 * @property int $requested_by_user_id
 * @property Carbon $requested_at
 * @property string $status
 * @property string|null $notes
 * @property string|null $cancelled_reason
 * @property Carbon|null $verified_at
 */
#[Fillable([
    'loan_transaction_id',
    'requested_by_user_id',
    'requested_at',
    'status',
    'notes',
    'cancelled_reason',
    'verified_at',
])]
class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LoanTransaction, $this> */
    public function loanTransaction(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return HasOne<ReturnInspection, $this> */
    public function inspection(): HasOne
    {
        return $this->hasOne(ReturnInspection::class);
    }
}
