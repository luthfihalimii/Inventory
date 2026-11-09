<?php

namespace Database\Factories;

use App\Models\LoanTransaction;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loan_transaction_id' => LoanTransaction::factory(),
            'requested_by_user_id' => User::factory(),
            'requested_at' => now(),
            'status' => 'pending',
            'notes' => null,
        ];
    }
}
