<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryUnit extends Model
{
    use HasFactory;

    public const CONDITIONS = ['GOOD', 'MINOR_DAMAGE', 'DAMAGED'];
    public const STATUSES = ['AVAILABLE', 'RESERVED', 'BORROWED', 'MAINTENANCE'];

    protected $fillable = [
        'inventory_item_id', 'kode_unit', 'harga_pembelian', 'tanggal_pembelian',
        'terakhir_dipelihara', 'jadwal_pemeliharaan_berikutnya', 'kondisi', 'status', 'catatan',
    ];

    protected $casts = [
        'harga_pembelian' => 'decimal:2',
        'tanggal_pembelian' => 'date',
        'terakhir_dipelihara' => 'date',
        'jadwal_pemeliharaan_berikutnya' => 'date',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'inventory_unit_id');
    }

    public function loanDetailUnits(): HasMany
    {
        return $this->hasMany(LoanDetailUnit::class, 'inventory_unit_id');
    }

    public function returnInspections(): HasMany
    {
        return $this->hasMany(ReturnUnitInspection::class, 'inventory_unit_id');
    }

    public function hasActiveMaintenance(): bool
    {
        return $this->maintenanceRecords()
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->exists();
    }

    public function hasOpenReturnIssue(): bool
    {
        return $this->loanDetailUnits()
            ->where('status', 'ACTIVE')
            ->whereHas('loanDetail.loanRequest', function ($query) {
                $query->where('status', 'RETURN_ISSUE');
            })
            ->exists();
    }

    /**
     * Sinkronisasi status operasional dari kondisi fisik dan proses aktif.
     * RESERVED/BORROWED tidak ditimpa oleh proses sinkronisasi umum.
     */
    public function synchronizeOperationalStatus(): void
    {
        if (
            $this->kondisi === 'DAMAGED'
            || $this->hasActiveMaintenance()
            || $this->hasOpenReturnIssue()
        ) {
            $this->update(['status' => 'MAINTENANCE']);
            return;
        }

        if (in_array($this->status, ['RESERVED', 'BORROWED'], true)) {
            return;
        }

        $this->update(['status' => 'AVAILABLE']);
    }
}
