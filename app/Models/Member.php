<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode', 'nrp', 'nama', 'divisi_id', 'jabatan_id', 'generasi_id', 'status',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'divisi_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'jabatan_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'generasi_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(User::class, 'member_id');
    }

    public function managerTerms(): HasMany
    {
        return $this->hasMany(ManagerTerm::class, 'member_id');
    }
}
