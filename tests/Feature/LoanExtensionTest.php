<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\LoanExtensionRequest;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoanExtensionTest extends TestCase
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
     * @return array{0: LoanTransaction, 1: User, 2: Member}
     */
    private function activeLoan(Item $item, int $quantity): array
    {
        $member = Member::factory()->create();
        $creator = $this->userWithRole('user', $member);

        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $creator->id,
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'borrowed',
            'checked_out_at' => now()->subDay(),
            'due_at' => now()->addDays(2),
        ]);

        LoanItem::factory()->create([
            'loan_transaction_id' => $loan->id,
            'item_id' => $item->id,
            'asset_id' => null,
            'quantity' => $quantity,
        ]);

        return [$loan, $creator, $member];
    }

    public function test_user_can_request_an_extension_before_the_due_date(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 2);
        $newDue = now()->addDays(4);

        $this->actingAs($creator)
            ->post(route('borrowing.extensions.store', $loan), [
                'requested_due_at' => $newDue->format('Y-m-d H:i:s'),
                'reason' => 'Kegiatan lapangan mundur.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $extension = LoanExtensionRequest::firstOrFail();
        $this->assertSame('pending', $extension->status);
        $this->assertSame($creator->id, $extension->requested_by_user_id);
        $this->assertSame(
            $newDue->format('Y-m-d H:i:s'),
            $extension->requested_due_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_extension_is_rejected_after_the_due_date(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 1);
        $loan->update(['due_at' => now()->subHour()]);

        $this->actingAs($creator)
            ->post(route('borrowing.extensions.store', $loan), [
                'requested_due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'reason' => 'Terlambat mengajukan.',
            ])
            ->assertSessionHasErrors('loan');

        $this->assertSame(0, LoanExtensionRequest::count());
    }

    public function test_extension_cannot_be_requested_for_someone_elses_loan(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan] = $this->activeLoan($item, 1);

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.extensions.store', $loan), [
                'requested_due_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'reason' => 'Bukan pinjaman saya.',
            ])
            ->assertForbidden();

        $this->assertSame(0, LoanExtensionRequest::count());
    }

    public function test_only_one_pending_extension_is_allowed_per_loan(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 1);

        LoanExtensionRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->post(route('borrowing.extensions.store', $loan), [
                'requested_due_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'reason' => 'Pengajuan kedua.',
            ])
            ->assertSessionHasErrors('loan');

        $this->assertSame(1, LoanExtensionRequest::count());
    }

    public function test_approval_moves_the_due_date_and_resets_the_reminder(): void
    {
        Mail::fake();

        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 2);
        $loan->update(['due_reminder_sent_at' => now()->subDay()]);

        $extension = LoanExtensionRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
            'current_due_at' => $loan->due_at,
            'requested_due_at' => now()->addDays(4),
        ]);

        $this->actingAs($this->userWithRole('wm'))
            ->post(route('admin.extensions.approve', $extension), [
                'decision_notes' => 'Disetujui.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $extension->refresh();
        $this->assertSame('approved', $extension->status);
        $this->assertSame('Disetujui.', $extension->decision_notes);
        $this->assertTrue($loan->fresh()->due_at->equalTo($extension->requested_due_at));
        $this->assertNull($loan->fresh()->due_reminder_sent_at);
        $this->assertSame(1, Notification::where('user_id', $creator->id)
            ->where('type', 'loan.extension_approved')
            ->count());
    }

    public function test_approval_is_blocked_when_the_extension_period_is_fully_booked(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);
        [$loan, $creator] = $this->activeLoan($item, 2);

        $extension = LoanExtensionRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
            'current_due_at' => $loan->due_at,
            'requested_due_at' => now()->addDays(5),
        ]);

        Reservation::factory()->create([
            'item_id' => $item->id,
            'status' => 'active',
            'quantity' => 1,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(4),
        ]);

        $originalDue = $loan->due_at;

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.extensions.approve', $extension), [])
            ->assertSessionHasErrors('extension');

        $this->assertSame('pending', $extension->fresh()->status);
        $this->assertTrue($loan->fresh()->due_at->equalTo($originalDue));
        $this->assertSame(
            1,
            app(AvailabilityService::class)->availableForPeriod(
                $item,
                $loan->due_at,
                $extension->requested_due_at,
            ),
        );
    }

    public function test_user_cannot_approve_an_extension(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 1);

        $extension = LoanExtensionRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
            'current_due_at' => $loan->due_at,
            'requested_due_at' => now()->addDays(4),
        ]);

        $this->actingAs($creator)
            ->post(route('admin.extensions.approve', $extension), [])
            ->assertForbidden();

        $this->assertSame('pending', $extension->fresh()->status);
    }

    public function test_rejection_keeps_the_due_date(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->activeLoan($item, 1);
        $dueAt = $loan->due_at;

        $extension = LoanExtensionRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
            'current_due_at' => $dueAt,
            'requested_due_at' => now()->addDays(4),
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.extensions.reject', $extension), [
                'decision_notes' => 'Barang dibutuhkan anggota lain.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $extension->fresh()->status);
        $this->assertTrue($loan->fresh()->due_at->equalTo($dueAt));
    }
}
