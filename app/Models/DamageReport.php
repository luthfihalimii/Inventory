<?php

namespace App\Models;

use Database\Factories\DamageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $loan_transaction_id
 * @property int|null $loan_item_id
 * @property int $item_id
 * @property int|null $asset_id
 * @property int|null $member_id
 * @property string $type
 * @property int|null $quantity
 * @property string $description
 * @property string $status
 * @property string|null $resolution
 * @property string|null $resolution_notes
 * @property string|null $cost
 * @property string|null $vendor
 * @property int|null $reported_by_user_id
 * @property Carbon $reported_at
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'loan_transaction_id',
    'loan_item_id',
    'item_id',
    'asset_id',
    'member_id',
    'type',
    'quantity',
    'description',
    'status',
    'resolution',
    'resolution_notes',
    'cost',
    'vendor',
    'reported_by_user_id',
    'reported_at',
    'resolved_by_user_id',
    'resolved_at',
])]
class DamageReport extends Model
{
    /** @use HasFactory<DamageReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost' => 'decimal:2',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
