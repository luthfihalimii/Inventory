<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_pengajuan',
        'user_id',
        'kategori',
        'waktu_mulai',
        'waktu_selesai',
        'catatan',
        'status',
        'diputuskan_oleh',
        'waktu_keputusan',
        'alasan_penolakan',
        'alasan_pembatalan',
        'dibatalkan_oleh',
        'waktu_pembatalan',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'waktu_keputusan' => 'datetime',
        'waktu_pembatalan' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function details(): HasMany
    {
        return $this->hasMany(LoanDetail::class, 'loan_request_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class, 'loan_request_id');
    }

    public function returnRecord(): HasOne
    {
        return $this->hasOne(ReturnRecord::class, 'loan_request_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'loan_request_id');
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(LoanCancellationRequest::class, 'loan_request_id');
    }

    public function pendingCancellationRequest(): HasOne
    {
        return $this->hasOne(LoanCancellationRequest::class, 'loan_request_id')->where('status', 'PENDING')->latestOfMany();
    }
}
