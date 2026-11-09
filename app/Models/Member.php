<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'member_code',
    'name',
    'nrp',
    'division_id',
    'generation',
    'position',
    'notes',
    'membership_status',
    'borrowing_status',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
        ];
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
