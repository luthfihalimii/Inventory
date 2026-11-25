<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_unit_id', 'tanggal_pemeliharaan', 'deskripsi', 'biaya', 'status', 'kondisi_setelah',
    ];

    protected $casts = [
        'tanggal_pemeliharaan' => 'date',
        'biaya' => 'decimal:2',
    ];

    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
