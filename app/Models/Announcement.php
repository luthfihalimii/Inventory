<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'judul',
        'isi',
        'kategori',
        'is_pinned',
        'dibuat_oleh',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];


    public function wordCount(): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($this->isi ?? ''), $matches);
        return count($matches[0] ?? []);
    }

    public function readTimeMinutes(): int
    {
        return max(1, (int) ceil($this->wordCount() / 200));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
