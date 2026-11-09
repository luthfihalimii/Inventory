<?php

namespace App\Models;

use Database\Factories\BorrowRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $borrow_request_id
 * @property int $item_id
 * @property int $quantity
 * @property string|null $notes
 */
#[Fillable(['borrow_request_id', 'item_id', 'quantity', 'notes'])]
class BorrowRequestItem extends Model
{
    /** @use HasFactory<BorrowRequestItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
