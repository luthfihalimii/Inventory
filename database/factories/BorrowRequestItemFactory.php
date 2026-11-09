<?php

namespace Database\Factories;

use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowRequestItem>
 */
class BorrowRequestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrow_request_id' => BorrowRequest::factory(),
            'item_id' => Item::factory(),
            'quantity' => 1,
            'notes' => null,
        ];
    }
}
