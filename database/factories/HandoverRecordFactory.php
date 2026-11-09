<?php

namespace Database\Factories;

use App\Models\HandoverRecord;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverRecord>
 */
class HandoverRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loan_transaction_id' => LoanTransaction::factory(),
            'officer_user_id' => User::factory(),
            'borrower_member_id' => Member::factory(),
            'handed_at' => now(),
            'checklist' => ['identity_verified' => true],
            'scan_results' => [],
            'borrower_confirmation_method' => 'on_site',
            'borrower_confirmed_at' => now(),
            'notes' => null,
        ];
    }
}
