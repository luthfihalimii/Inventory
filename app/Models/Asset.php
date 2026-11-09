<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'item_id',
    'asset_code',
    'serial_number',
    'qr_token',
    'condition',
    'status',
    'notes',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
