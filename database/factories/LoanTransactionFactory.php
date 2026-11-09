<?php

namespace Database\Factories;

use App\Models\BorrowRequest;
use App\Models\LoanTransaction;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanTransaction>
 */
class LoanTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'borrow_request_id' => BorrowRequest::factory(),
            'member_id' => Member::factory(),
            'status' => 'reserved',
            'due_at' => now()->addDays(5),
        ];
    }
}
