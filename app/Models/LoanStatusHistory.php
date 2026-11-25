<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_request_id',
        'status_sebelumnya',
        'status_sesudahnya',
        'diubah_oleh',
        'waktu_perubahan',
    ];

    protected $casts = [
        'waktu_perubahan' => 'datetime',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class, 'loan_request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
