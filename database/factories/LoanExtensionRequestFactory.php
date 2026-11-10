<?php

namespace Database\Factories;

use App\Models\LoanExtensionRequest;
use App\Models\LoanTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanExtensionRequest>
 */
class LoanExtensionRequestFactory extends Factory
{
    public function definition(): array
    {
        $dueAt = now()->addDays(3);

        return [
            'loan_transaction_id' => LoanTransaction::factory(),
            'requested_by_user_id' => User::factory(),
            'current_due_at' => $dueAt,
            'requested_due_at' => $dueAt->addDays(2),
            'reason' => 'Kegiatan lapangan mundur satu hari.',
            'status' => 'pending',
        ];
    }
}
