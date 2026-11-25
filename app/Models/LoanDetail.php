<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanDetail extends Model
{
    use HasFactory;

    protected $fillable = ['loan_request_id', 'inventory_item_id', 'jumlah'];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class, 'loan_request_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function allocatedUnits(): HasMany
    {
        return $this->hasMany(LoanDetailUnit::class, 'loan_detail_id')
            ->where('status', 'ACTIVE');
    }

    public function allocationHistory(): HasMany
    {
        return $this->hasMany(LoanDetailUnit::class, 'loan_detail_id');
    }
}
