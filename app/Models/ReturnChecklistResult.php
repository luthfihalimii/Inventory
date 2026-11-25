<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnChecklistResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_record_id',
        'pemeriksaan_ke',
        'nama_pemeriksaan',
        'is_checked',
        'catatan',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
    ];

    public function returnRecord(): BelongsTo
    {
        return $this->belongsTo(ReturnRecord::class, 'return_record_id');
    }
}
