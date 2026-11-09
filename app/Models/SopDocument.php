<?php

namespace App\Models;

use Database\Factories\SopDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $version
 * @property string $title
 * @property string $content
 * @property Carbon $effective_at
 * @property bool $is_active
 */
#[Fillable(['version', 'title', 'content', 'effective_at', 'is_active', 'created_by_user_id'])]
class SopDocument extends Model
{
    /** @use HasFactory<SopDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<SopAcknowledgement, $this> */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(SopAcknowledgement::class);
    }
}
