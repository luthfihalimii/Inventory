<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LockerLocation extends Model
{
    use HasFactory;

    protected $fillable = ['nama', 'status_aktif'];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class, 'lokasi_loker_id');
    }
}
