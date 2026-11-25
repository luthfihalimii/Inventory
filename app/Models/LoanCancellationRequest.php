<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCancellationRequest extends Model
{
    protected $fillable = [
        'loan_request_id', 'requested_by', 'reason', 'status',
        'reviewed_by', 'review_reason', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class, 'loan_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
