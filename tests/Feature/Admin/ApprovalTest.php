<?php

namespace Tests\Feature\Admin;

use App\Models\Approval;
use App\Models\Asset;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function approver(string $role): User
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function requestWithItem(Item $item, int $quantity = 1, ?Member $member = null, ?User $creator = null): BorrowRequest
    {
        $member ??= Member::factory()->create();
        $creator ??= User::factory()->create(['member_id' => $member->id]);

        $request = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $creator->id,
        ]);

        $request->items()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        return $request;
    }

    public function test_admin_approval_reserves_quantity_and_creates_loan(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $request = $this->requestWithItem($item, 2);
        $admin = $this->approver('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $request))
            ->assertRedirect(route('admin.approval.index'));

        $this->assertSame('approved', $request->fresh()->status);

        $approval = Approval::where('borrow_request_id', $request->id)->firstOrFail();
        $this->assertSame('approved', $approval->decision);
        $this->assertSame('admin_korlab', $approval->approver_role);
        $this->assertSame($admin->member_id, $approval->approver_member_id);

        $reservation = Reservation::where('borrow_request_id', $request->id)->firstOrFail();
        $this->assertSame(2, $reservation->quantity);
        $this->assertNull($reservation->asset_id);
        $this->assertSame('active', $reservation->status);

        $this->assertSame('reserved', LoanTransaction::where('borrow_request_id', $request->id)->value('status'));
    }

    public function test_wm_approval_allocates_serialized_units(): void
    {
        $item = Item::factory()->create();
        Asset::factory()->for($item)->create(['status' => 'available']);
        Asset::factory()->for($item)->create(['status' => 'available']);
        $request = $this->requestWithItem($item, 2);

        $this->actingAs($this->approver('wm'))
            ->post(route('admin.approval.approve', $request))
            ->assertRedirect();

        $reservations = Reservation::where('borrow_request_id', $request->id)->get();

        $this->assertCount(2, $reservations);
        $this->assertNotNull($reservations[0]->asset_id);
        $this->assertNotSame($reservations[0]->asset_id, $reservations[1]->asset_id);
        $this->assertSame('wm', Approval::where('borrow_request_id', $request->id)->value('approver_role'));
    }

    public function test_approver_cannot_decide_their_own_request(): void
    {
        $member = Member::factory()->create();
        $admin = User::factory()->create(['member_id' => $member->id]);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin_korlab']));
        $request = $this->requestWithItem(Item::factory()->quantity()->create(), 1, $member, $admin);

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $request))
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending_approval', $request->fresh()->status);
        $this->assertSame(0, Approval::count());
    }

    public function test_first_decision_wins(): void
    {
        $request = $this->requestWithItem(Item::factory()->quantity()->create(['quantity_total' => 5]));

        $this->actingAs($this->approver('admin_korlab'))
            ->post(route('admin.approval.approve', $request))
            ->assertRedirect();

        $this->actingAs($this->approver('wm'))
            ->post(route('admin.approval.reject', $request), ['notes' => 'Terlambat.'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(1, Approval::count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $request = $this->requestWithItem(Item::factory()->quantity()->create());
        $admin = $this->approver('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.approval.reject', $request))
            ->assertSessionHasErrors('notes');

        $this->actingAs($admin)
            ->post(route('admin.approval.reject', $request), ['notes' => 'Barang dipakai.'])
            ->assertRedirect();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, LoanTransaction::count());
    }

    public function test_approval_fails_atomically_when_stock_is_gone(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);
        $request = $this->requestWithItem($item, 3);
        $admin = $this->approver('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.approval.approve', $request))
            ->assertSessionHasErrors('items');

        $this->assertSame('pending_approval', $request->fresh()->status);
        $this->assertSame(0, Approval::count());
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, LoanTransaction::count());
    }

    public function test_inactive_members_cannot_be_approved(): void
    {
        $request = $this->requestWithItem(
            Item::factory()->quantity()->create(),
            1,
            Member::factory()->inactive()->create(),
        );

        $this->actingAs($this->approver('admin_korlab'))
            ->post(route('admin.approval.approve', $request))
            ->assertSessionHasErrors('member');

        $this->assertSame('pending_approval', $request->fresh()->status);
    }

    public function test_only_approvers_can_access_the_queue(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user']));

        $this->actingAs($user)->get(route('admin.approval.index'))->assertForbidden();

        $this->actingAs($this->approver('wm'))->get(route('admin.approval.index'))->assertOk();
    }

    public function test_urgent_requests_are_listed_first(): void
    {
        $scheduled = $this->requestWithItem(Item::factory()->quantity()->create());
        $urgent = $this->requestWithItem(Item::factory()->quantity()->create());
        $urgent->update(['category' => 'urgent', 'purpose' => 'Mendesak', 'submitted_at' => now()->addMinute()]);
        $scheduled->update(['submitted_at' => now()->subMinute()]);

        $this->actingAs($this->approver('admin_korlab'))
            ->get(route('admin.approval.index'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/approval/Index')
                ->where('requests.data.0.id', $urgent->id)
                ->where('requests.data.1.id', $scheduled->id));
    }
}
