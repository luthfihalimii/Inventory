<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueReminderTest extends TestCase
{
    use RefreshDatabase;

    private function borrowedLoan(Member $member, CarbonInterface $dueAt, string $status = 'borrowed'): LoanTransaction
    {
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create()->id,
        ]);

        return LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => $status,
            'checked_out_at' => now()->subDay(),
            'due_at' => $dueAt,
        ]);
    }

    public function test_due_tomorrow_sends_one_reminder_per_member(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);

        // 10:00 besok waktu aplikasi (Asia/Jakarta).
        $loan = $this->borrowedLoan($member, now()->addDay()->setTime(10, 0));

        $this->artisan('inventory:due-reminders')->assertSuccessful();

        $this->assertNotNull($loan->fresh()->due_reminder_sent_at);
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'loan.due_reminder')
            ->firstOrFail();
        $this->assertSame($loan->id, $notification->data['loan_transaction_id']);

        // Scheduler jalan ulang tidak boleh mengirim dobel.
        $this->artisan('inventory:due-reminders')->assertSuccessful();
        $this->assertSame(1, Notification::where('type', 'loan.due_reminder')->count());
    }

    public function test_later_due_dates_and_other_statuses_are_ignored(): void
    {
        $member = Member::factory()->create();
        User::factory()->create(['member_id' => $member->id]);

        $later = $this->borrowedLoan($member, now()->addDays(3));
        $pending = $this->borrowedLoan($member, now()->addDay()->setTime(10, 0), 'return_pending_verification');
        $overdue = $this->borrowedLoan($member, now()->subDay());

        $this->artisan('inventory:due-reminders')->assertSuccessful();

        $this->assertNull($later->fresh()->due_reminder_sent_at);
        $this->assertNull($pending->fresh()->due_reminder_sent_at);
        $this->assertNull($overdue->fresh()->due_reminder_sent_at);
        $this->assertSame(0, Notification::where('type', 'loan.due_reminder')->count());
    }
}
