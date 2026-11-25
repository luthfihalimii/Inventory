<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'member_id', 'username', 'password', 'status', 'must_change_password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'must_change_password' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function managerTerms(): HasMany
    {
        return $this->hasMany(ManagerTerm::class, 'user_id');
    }

    public function activeManagerTerm(): ?ManagerTerm
    {
        return $this->managerTerms()->whereNull('ended_at')->latest('started_at')->first();
    }

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class, 'user_id');
    }

    public function decidedLoanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class, 'diputuskan_oleh');
    }

    public function loanStatusHistories(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class, 'diubah_oleh');
    }

    public function submittedReturns(): HasMany
    {
        return $this->hasMany(ReturnRecord::class, 'diajukan_oleh');
    }

    public function verifiedReturns(): HasMany
    {
        return $this->hasMany(ReturnRecord::class, 'diverifikasi_oleh');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'dibuat_oleh');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    /** Compatibility accessors for older Blade references. */
    public function getKodeAttribute(): ?string { return $this->member?->kode; }
    public function getNrpAttribute(): ?string { return $this->member?->nrp; }
    public function getNamaAttribute(): ?string { return $this->member?->nama; }
    public function getDivisiIdAttribute(): ?int { return $this->member?->divisi_id; }
    public function getJabatanIdAttribute(): ?int { return $this->member?->jabatan_id; }
    public function getGenerasiIdAttribute(): ?int { return $this->member?->generasi_id; }
    public function getDivisionAttribute(): ?Division { return $this->member?->division; }
    public function getPositionAttribute(): ?Position { return $this->member?->position; }
    public function getGenerationAttribute(): ?Generation { return $this->member?->generation; }
}
