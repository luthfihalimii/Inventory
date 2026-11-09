<?php

namespace Database\Factories;

use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Item;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrow_request_id' => BorrowRequest::factory(),
            'borrow_request_item_id' => BorrowRequestItem::factory(),
            'item_id' => Item::factory(),
            'asset_id' => null,
            'quantity' => 1,
            'quantity_fulfilled' => 0,
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(5),
            'status' => 'active',
        ];
    }
}
