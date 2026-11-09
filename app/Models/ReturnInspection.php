<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $return_request_id
 * @property int $officer_user_id
 * @property Carbon $inspected_at
 * @property string|null $notes
 * @property array<int, mixed>|null $photos
 */
#[Fillable(['return_request_id', 'officer_user_id', 'inspected_at', 'notes', 'photos'])]
class ReturnInspection extends Model
{
    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'photos' => 'array',
        ];
    }

    /** @return BelongsTo<ReturnRequest, $this> */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    /** @return HasMany<ReturnInspectionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnInspectionItem::class);
    }
}
