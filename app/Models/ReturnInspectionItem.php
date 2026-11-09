<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $return_inspection_id
 * @property int $loan_item_id
 * @property string $condition
 * @property int $quantity_returned
 * @property string|null $notes
 */
#[Fillable(['return_inspection_id', 'loan_item_id', 'condition', 'quantity_returned', 'notes'])]
class ReturnInspectionItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
        ];
    }

    /** @return BelongsTo<ReturnInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ReturnInspection::class, 'return_inspection_id');
    }

    /** @return BelongsTo<LoanItem, $this> */
    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }
}
