<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Asset;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\SopDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningTest extends TestCase
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
     * @return array{0: BorrowRequest, 1: Member}
     */
    private function requestWithItem(Item $item, int $quantity = 1, ?Member $member = null): array
    {
        $member ??= Member::factory()->create();
        $creator = User::factory()->create(['member_id' => $member->id]);

        $request = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $creator->id,
        ]);

        $request->items()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        return [$request, $member];
    }

    public function test_self_approval_across_accounts_with_the_same_member_is_rejected(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$request, $member] = $this->requestWithItem($item, 1);

        // Second account owns the same member, with approver rights.
        $otherAccount = $this->userWithRole('admin_korlab', $member);

        $this->actingAs($otherAccount)
            ->post(route('admin.approval.approve', $request))
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending_approval', $request->fresh()->status);
        $this->assertSame(0, Approval::count());
    }

    public function test_overdue_member_cannot_submit_through_another_account(): void
    {
        SopDocument::factory()->active()->create();
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);

        $member = Member::factory()->create();
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => BorrowRequest::factory()->approved()->create(['member_id' => $member->id])->id,
            'member_id' => $member->id,
            'status' => 'borrowed',
            'checked_out_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
        ]);
        LoanItem::factory()->create([
            'loan_transaction_id' => $loan->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        // First account triggers the block; second account tries to slip through.
        $secondAccount = $this->userWithRole('user', $member);

        $this->actingAs($secondAccount)
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
        $this->assertSame('overdue', $loan->fresh()->status);
    }

    public function test_last_unit_goes_to_exactly_one_request(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 1]);
        [$first] = $this->requestWithItem($item, 1);
        [$second] = $this->requestWithItem($item, 1);
        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $first))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $second))
            ->assertSessionHasErrors('items');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending_approval', $second->fresh()->status);
        $this->assertSame(1, Approval::count());
        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, LoanTransaction::count());
    }

    public function test_quantity_reservations_for_overlapping_periods_never_exceed_stock(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 3]);
        [$first] = $this->requestWithItem($item, 2);
        [$second] = $this->requestWithItem($item, 2);
        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $first))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $second))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, Reservation::where('status', 'active')->count());
        $this->assertSame(2, (int) Reservation::where('status', 'active')->sum('quantity'));
        $this->assertSame('pending_approval', $second->fresh()->status);
    }

    public function test_unit_due_before_a_new_period_can_be_reserved_for_later(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 1]);

        $member = Member::factory()->create();
        $currentLoan = LoanTransaction::factory()->create([
            'borrow_request_id' => BorrowRequest::factory()->approved()->create(['member_id' => $member->id])->id,
            'member_id' => $member->id,
            'status' => 'borrowed',
            'checked_out_at' => now()->subDay(),
            'due_at' => now()->addDay(),
        ]);
        LoanItem::factory()->create([
            'loan_transaction_id' => $currentLoan->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        $later = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
            'pickup_at' => now()->addDays(5),
            'return_at' => now()->addDays(7),
        ]);
        $later->items()->create(['item_id' => $item->id, 'quantity' => 1]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.approval.approve', $later))
            ->assertRedirect();

        $this->assertSame('approved', $later->fresh()->status);
        $this->assertSame(1, Reservation::where('borrow_request_id', $later->id)->count());
    }

    public function test_scan_rejects_units_that_are_damaged_lost_or_inactive(): void
    {
        $item = Item::factory()->create();
        [$loan] = $this->approvedSerializedLoan($item);

        foreach (['damaged', 'lost', 'maintenance', 'inactive'] as $status) {
            $asset = Asset::factory()->for($item)->create(['status' => $status]);

            $this->actingAs($this->userWithRole('admin_korlab'))
                ->postJson(route('admin.checkout.scan', $loan), ['token' => $asset->qr_token])
                ->assertStatus(422)
                ->assertJson(['valid' => false]);
        }
    }

    public function test_checkout_cannot_exceed_the_reserved_quantity(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
            'pickup_at' => now(),
            'return_at' => now()->addDays(2),
        ]);
        $line = $request->items()->create(['item_id' => $item->id, 'quantity' => 2]);
        $reservation = Reservation::factory()->create([
            'borrow_request_id' => $request->id,
            'borrow_request_item_id' => $line->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'status' => 'active',
            'start_at' => $request->pickup_at,
            'end_at' => $request->return_at,
        ]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => $request->return_at,
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.checkout.store', $loan), [
                'lines' => [[
                    'reservation_id' => $reservation->id,
                    'quantity' => 3,
                    'condition_out' => 'good',
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame('reserved', $loan->fresh()->status);
        $this->assertSame('active', $reservation->fresh()->status);
        $this->assertSame(0, LoanItem::where('loan_transaction_id', $loan->id)->count());
    }

    public function test_second_approver_cannot_create_a_second_reservation(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$request] = $this->requestWithItem($item, 1);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.approval.approve', $request))
            ->assertRedirect();

        $this->actingAs($this->userWithRole('wm'))
            ->post(route('admin.approval.reject', $request), ['notes' => 'Batal.'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(1, Approval::count());
        $this->assertSame(1, Reservation::where('borrow_request_id', $request->id)->count());
        $this->assertSame(1, LoanTransaction::where('borrow_request_id', $request->id)->count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    /**
     * @return array{0: LoanTransaction, 1: Reservation}
     */
    private function approvedSerializedLoan(Item $item): array
    {
        $member = Member::factory()->create();
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
            'pickup_at' => now(),
            'return_at' => now()->addDays(2),
        ]);
        $line = $request->items()->create(['item_id' => $item->id, 'quantity' => 1]);
        $asset = Asset::factory()->for($item)->create(['status' => 'available']);
        $reservation = Reservation::factory()->create([
            'borrow_request_id' => $request->id,
            'borrow_request_item_id' => $line->id,
            'item_id' => $item->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'status' => 'active',
            'start_at' => $request->pickup_at,
            'end_at' => $request->return_at,
        ]);
        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => $request->return_at,
        ]);

        return [$loan, $reservation];
    }
}
