<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property int $borrow_request_id
 * @property int $sop_document_id
 * @property Carbon $accepted_at
 * @property string|null $ip_address
 */
#[Fillable(['member_id', 'borrow_request_id', 'sop_document_id', 'accepted_at', 'ip_address'])]
class SopAcknowledgement extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @return BelongsTo<SopDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SopDocument::class, 'sop_document_id');
    }
}
