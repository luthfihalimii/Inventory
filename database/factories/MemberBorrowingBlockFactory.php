<?php

namespace Database\Factories;

use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberBorrowingBlock>
 */
class MemberBorrowingBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'loan_transaction_id' => LoanTransaction::factory(),
            'reason' => 'Terlambat mengembalikan barang.',
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => now(),
        ]);
    }
}
