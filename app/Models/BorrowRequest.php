<?php

namespace App\Models;

use Database\Factories\BorrowRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $reference_no
 * @property int $member_id
 * @property int $created_by_user_id
 * @property string $category
 * @property Carbon $pickup_at
 * @property Carbon $return_at
 * @property string|null $usage_location
 * @property string|null $purpose
 * @property string|null $notes
 * @property string $status
 * @property Carbon $submitted_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'reference_no',
    'member_id',
    'created_by_user_id',
    'category',
    'pickup_at',
    'return_at',
    'usage_location',
    'purpose',
    'notes',
    'status',
    'submitted_at',
    'cancelled_at',
    'cancellation_reason',
])]
class BorrowRequest extends Model
{
    /** @use HasFactory<BorrowRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<BorrowRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BorrowRequestItem::class);
    }

    /** @return HasOne<Approval, $this> */
    public function approval(): HasOne
    {
        return $this->hasOne(Approval::class);
    }

    /** @return HasOne<LoanTransaction, $this> */
    public function loanTransaction(): HasOne
    {
        return $this->hasOne(LoanTransaction::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
