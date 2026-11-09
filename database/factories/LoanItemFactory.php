<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanItem>
 */
class LoanItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loan_transaction_id' => LoanTransaction::factory(),
            'item_id' => Item::factory(),
            'asset_id' => null,
            'quantity' => 1,
            'condition_out' => 'good',
            'notes' => null,
        ];
    }
}
