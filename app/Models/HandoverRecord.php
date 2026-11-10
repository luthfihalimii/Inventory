<?php

namespace App\Models;

use Database\Factories\HandoverRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_transaction_id
 * @property int $officer_user_id
 * @property int $borrower_member_id
 * @property Carbon $handed_at
 * @property array<string, mixed>|null $checklist
 * @property array<int, mixed>|null $scan_results
 * @property array<int, string>|null $photos
 * @property string $borrower_confirmation_method
 * @property Carbon|null $borrower_confirmed_at
 * @property string|null $notes
 */
#[Fillable([
    'loan_transaction_id',
    'officer_user_id',
    'borrower_member_id',
    'handed_at',
    'checklist',
    'scan_results',
    'photos',
    'borrower_confirmation_method',
    'borrower_confirmed_at',
    'notes',
])]
class HandoverRecord extends Model
{
    /** @use HasFactory<HandoverRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'handed_at' => 'datetime',
            'checklist' => 'array',
            'scan_results' => 'array',
            'photos' => 'array',
            'borrower_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LoanTransaction, $this> */
    public function loanTransaction(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'borrower_member_id');
    }
}
