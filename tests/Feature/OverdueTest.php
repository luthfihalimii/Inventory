<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SopDocument;
use App\Models\User;
use App\Services\OverdueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OverdueTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?Member $member = null): User
    {
        $member ??= Member::factory()->create();

        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    /**
     * A borrowed loan whose due date is already in the past.
     *
     * @return array{0: LoanTransaction, 1: Member}
     */
    private function overdueLoan(string $status = 'borrowed', ?Member $member = null): array
    {
        $member ??= Member::factory()->create();

        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => $status,
            'checked_out_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
        ]);

        return [$loan, $member];
    }

    public function test_command_flags_overdue_loans_and_blocks_the_member(): void
    {
        [$loan, $member] = $this->overdueLoan();
        $this->userWithRole('user', $member);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $loan->refresh();
        $this->assertSame('overdue', $loan->status);
        $this->assertNotNull($loan->overdue_at);
        $this->assertSame('borrowing_blocked', $member->fresh()->borrowing_status);
        $this->assertSame(1, MemberBorrowingBlock::where('member_id', $member->id)->whereNull('ended_at')->count());
        $this->assertSame(1, AuditLog::where('action', 'loan.overdue')->count());
    }

    public function test_running_the_scheduler_twice_does_not_duplicate_blocks_or_notifications(): void
    {
        [$loan, $member] = $this->overdueLoan();
        $user = $this->userWithRole('user', $member);

        $this->artisan('inventory:overdue')->assertSuccessful();
        $this->artisan('inventory:overdue')->assertSuccessful();

        $this->assertSame(1, MemberBorrowingBlock::where('loan_transaction_id', $loan->id)->count());
        $this->assertSame(1, Notification::where('user_id', $user->id)->where('type', 'loan.overdue')->count());
        $this->assertSame(1, AuditLog::where('action', 'loan.overdue')->count());
    }

    public function test_loan_awaiting_verification_keeps_its_status_but_is_flagged_overdue(): void
    {
        [$loan, $member] = $this->overdueLoan('return_pending_verification');

        $this->artisan('inventory:overdue')->assertSuccessful();

        $loan->refresh();
        $this->assertSame('return_pending_verification', $loan->status);
        $this->assertNotNull($loan->overdue_at);
        $this->assertSame('borrowing_blocked', $member->fresh()->borrowing_status);
    }

    public function test_loans_that_are_not_yet_due_are_ignored(): void
    {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create(['member_id' => $member->id]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'borrowed',
            'due_at' => now()->addDay(),
        ]);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $this->assertSame('borrowed', $loan->fresh()->status);
        $this->assertNull($loan->fresh()->overdue_at);
        $this->assertSame(0, MemberBorrowingBlock::count());
    }

    public function test_reserved_loans_are_never_marked_overdue(): void
    {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'pickup_at' => now()->addDays(2),
            'return_at' => now()->addDays(4),
        ]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $this->assertSame('reserved', $loan->fresh()->status);
        $this->assertSame(0, MemberBorrowingBlock::count());
    }

    public function test_unclaimed_reservation_expires_without_blocking_the_member(): void
    {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'pickup_at' => now()->subDay(),
            'return_at' => now()->addDay(),
        ]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => now()->addDay(),
        ]);
        Reservation::factory()->create([
            'borrow_request_id' => $request->id,
            'status' => 'active',
        ]);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $this->assertSame('cancelled', $loan->fresh()->status);
        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame('released', Reservation::firstOrFail()->status);
        $this->assertSame(0, MemberBorrowingBlock::count());
        $this->assertSame('eligible', $member->fresh()->borrowing_status);
        $this->assertSame(1, AuditLog::where('action', 'borrow_request.expired')->count());
    }

    public function test_reservation_is_kept_until_the_end_of_the_pickup_day(): void
    {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'pickup_at' => now()->startOfDay(),
            'return_at' => now()->addDays(2),
        ]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
        ]);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $this->assertSame('reserved', $loan->fresh()->status);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_submission_is_blocked_synchronously_even_when_the_scheduler_has_not_run(): void
    {
        SopDocument::factory()->active()->create();
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [, $member] = $this->overdueLoan();
        $user = $this->userWithRole('user', $member);

        $this->actingAs($user)
            ->post(route('borrowing.store'), [
                'category' => 'scheduled',
                'pickup_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'usage_location' => 'Lab Jurnalistik',
                'purpose' => null,
                'notes' => null,
                'sop_accepted' => true,
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame(0, BorrowRequest::where('status', 'pending_approval')->count());
        $this->assertSame(1, MemberBorrowingBlock::where('member_id', $member->id)->count());

        // The synchronous guard flags the loan even though the scheduler never ran.
        $this->assertSame('overdue', LoanTransaction::where('member_id', $member->id)->firstOrFail()->status);
        $this->assertSame(1, AuditLog::where('action', 'loan.overdue')->count());
    }

    public function test_block_is_released_after_return_verification(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $member] = $this->overdueLoan();
        $creator = $this->userWithRole('user', $member);

        $this->artisan('inventory:overdue')->assertSuccessful();

        $loan->update(['status' => 'return_pending_verification']);

        $returnRequest = ReturnRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
        ]);

        $loanItem = LoanItem::factory()->create([
            'loan_transaction_id' => $loan->id,
            'item_id' => $item->id,
            'asset_id' => null,
            'quantity' => 2,
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [[
                    'loan_item_id' => $loanItem->id,
                    'condition' => 'good',
                    'quantity_returned' => 2,
                ]],
            ])
            ->assertRedirect(route('admin.returns.index'));

        $this->assertSame('completed', $loan->fresh()->status);
        $this->assertNotNull(MemberBorrowingBlock::where('member_id', $member->id)->firstOrFail()->ended_at);
        $this->assertSame('eligible', $member->fresh()->borrowing_status);
        $this->assertSame(1, AuditLog::where('action', 'member.unblocked')->count());
    }

    public function test_block_stays_when_another_overdue_loan_is_still_open(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$first, $member] = $this->overdueLoan();
        [$second] = $this->overdueLoan('borrowed', $member);
        $creator = $this->userWithRole('user', $member);

        $this->artisan('inventory:overdue')->assertSuccessful();
        $this->assertSame(2, MemberBorrowingBlock::where('member_id', $member->id)->whereNull('ended_at')->count());

        $first->update(['status' => 'return_pending_verification']);

        $returnRequest = ReturnRequest::factory()->create([
            'loan_transaction_id' => $first->id,
            'requested_by_user_id' => $creator->id,
        ]);

        $loanItem = LoanItem::factory()->create([
            'loan_transaction_id' => $first->id,
            'item_id' => $item->id,
            'asset_id' => null,
            'quantity' => 2,
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [[
                    'loan_item_id' => $loanItem->id,
                    'condition' => 'good',
                    'quantity_returned' => 2,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame('borrowing_blocked', $member->fresh()->borrowing_status);
        $this->assertSame(1, MemberBorrowingBlock::where('member_id', $member->id)->whereNull('ended_at')->count());
        $this->assertSame(0, AuditLog::where('action', 'member.unblocked')->count());
    }

    public function test_overdue_member_cannot_be_approved(): void
    {
        [$loan, $member] = $this->overdueLoan();
        $loan->update(['status' => 'borrowed']);

        $admin = $this->userWithRole('admin_korlab');

        $pending = BorrowRequest::factory()->create(['member_id' => $member->id]);
        BorrowRequestItem::factory()->create([
            'borrow_request_id' => $pending->id,
            'item_id' => Item::factory()->quantity()->create(['quantity_total' => 3])->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $pending))
            ->assertSessionHasErrors('member');

        $this->assertSame('pending_approval', $pending->fresh()->status);
    }

    public function test_request_form_lists_the_blocking_loans(): void
    {
        SopDocument::factory()->active()->create();
        [$loan, $member] = $this->overdueLoan();
        $user = $this->userWithRole('user', $member);

        $this->artisan('inventory:overdue')->assertSuccessful();
        $loan->borrowRequest()->update(['reference_no' => 'REQ-20260101-000001']);

        $this->actingAs($user)
            ->get(route('borrowing.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('borrowing/Create')
                ->has('blocks', 1)
                ->where('blocks.0.reference_no', 'REQ-20260101-000001'));
    }

    public function test_request_form_has_no_blocks_for_an_eligible_member(): void
    {
        SopDocument::factory()->active()->create();

        $this->actingAs($this->userWithRole('user'))
            ->get(route('borrowing.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('borrowing/Create')
                ->has('blocks', 0));
    }

    public function test_overdue_service_is_injectable_and_idempotent(): void
    {
        [$loan, $member] = $this->overdueLoan();
        $service = app(OverdueService::class);

        $first = $service->markOverdue();
        $second = $service->markOverdue();

        $this->assertSame(1, $first['overdue']);
        $this->assertSame(0, $second['overdue']);
        $this->assertSame(1, $first['blocks']);
        $this->assertSame(1, $second['blocks']);
        $this->assertSame(1, MemberBorrowingBlock::where('member_id', $member->id)->count());
    }
}
