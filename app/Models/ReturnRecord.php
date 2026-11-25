<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_request_id', 'diajukan_oleh', 'diverifikasi_oleh', 'waktu_pengajuan',
        'waktu_verifikasi', 'waktu_pengembalian_diminta', 'status', 'catatan_pengguna', 'catatan_admin',
    ];

    protected $casts = [
        'waktu_pengajuan' => 'datetime',
        'waktu_pengembalian_diminta' => 'datetime',
        'waktu_verifikasi' => 'datetime',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class, 'loan_request_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReturnPhoto::class, 'return_record_id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(ReturnChecklistResult::class, 'return_record_id');
    }

    public function unitInspections(): HasMany
    {
        return $this->hasMany(ReturnUnitInspection::class, 'return_record_id');
    }
}
