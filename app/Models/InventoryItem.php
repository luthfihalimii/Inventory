<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_item',
        'nama',
        'deskripsi',
        'kategori_id',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'kategori_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class, 'inventory_item_id');
    }

    public function loanDetails(): HasMany
    {
        return $this->hasMany(LoanDetail::class, 'inventory_item_id');
    }
}
