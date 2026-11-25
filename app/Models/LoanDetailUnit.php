<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDetailUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_detail_id', 'inventory_unit_id', 'status', 'released_by', 'released_at', 'release_reason',
    ];

    protected $casts = ['released_at' => 'datetime'];

    public function loanDetail(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_detail_id');
    }

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
