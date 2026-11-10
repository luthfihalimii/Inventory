<?php

namespace App\Models;

use Database\Factories\LoanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $loan_transaction_id
 * @property int $item_id
 * @property int|null $asset_id
 * @property int $quantity
 * @property int $quantity_returned
 * @property string $condition_out
 * @property string|null $notes
 */
#[Fillable(['loan_transaction_id', 'item_id', 'asset_id', 'quantity', 'quantity_returned', 'condition_out', 'notes'])]
class LoanItem extends Model
{
    /** @use HasFactory<LoanItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
        ];
    }

    /** @return BelongsTo<LoanTransaction, $this> */
    public function loanTransaction(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
