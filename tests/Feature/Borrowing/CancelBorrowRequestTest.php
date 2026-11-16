<?php

namespace Tests\Feature\Borrowing;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelBorrowRequestTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?Member $member = null): User
    {
        $member ??= Member::factory()->create();

        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    public function test_owner_can_cancel_a_pending_request(): void
    {
        $member = Member::factory()->create();
        $user = $this->userWithRole('user', $member);
        $request = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('borrowing.cancel', $request), ['reason' => 'Kegiatan batal.'])
            ->assertRedirect();

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame('Kegiatan batal.', $request->fresh()->cancellation_reason);
        $this->assertSame(1, AuditLog::where('action', 'borrow_request.cancelled')->count());
    }

    public function test_approved_request_releases_reservations_and_loan(): void
    {
        $member = Member::factory()->create();
        $user = $this->userWithRole('user', $member);
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
        ]);
        $line = $request->items()->create(['item_id' => $item->id, 'quantity' => 2]);

        $reservation = Reservation::factory()->create([
            'borrow_request_id' => $request->id,
            'borrow_request_item_id' => $line->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'start_at' => $request->pickup_at,
            'end_at' => $request->return_at,
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->put(route('borrowing.cancel', $request), ['reason' => 'Barang rusak.'])
            ->assertRedirect();

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertSame('cancelled', $loan->fresh()->status);
    }

    public function test_staff_can_cancel_a_pending_request(): void
    {
        $request = BorrowRequest::factory()->create();
        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->put(route('borrowing.cancel', $request), ['reason' => 'Dibatalkan admin.'])
            ->assertRedirect();

        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame('Dibatalkan admin.', $request->fresh()->cancellation_reason);
    }

    public function test_other_users_cannot_cancel(): void
    {
        $request = BorrowRequest::factory()->create();

        $this->actingAs($this->userWithRole('user'))
            ->put(route('borrowing.cancel', $request))
            ->assertForbidden();

        $this->assertSame('pending_approval', $request->fresh()->status);
    }

    public function test_rejected_requests_cannot_be_cancelled(): void
    {
        $member = Member::factory()->create();
        $user = $this->userWithRole('user', $member);
        $request = BorrowRequest::factory()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($user)
            ->put(route('borrowing.cancel', $request), ['reason' => 'Sudah ditolak.'])
            ->assertSessionHasErrors('status');

        $this->assertSame('rejected', $request->fresh()->status);
    }
}
