<?php

namespace App\Models;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $borrow_request_id
 * @property int $borrow_request_item_id
 * @property int $item_id
 * @property int|null $asset_id
 * @property int $quantity
 * @property int $quantity_fulfilled
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string $status
 */
#[Fillable([
    'borrow_request_id',
    'borrow_request_item_id',
    'item_id',
    'asset_id',
    'quantity',
    'quantity_fulfilled',
    'start_at',
    'end_at',
    'status',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_fulfilled' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @return BelongsTo<BorrowRequestItem, $this> */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(BorrowRequestItem::class, 'borrow_request_item_id');
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
