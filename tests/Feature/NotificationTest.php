<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Item;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?Member $member = null): User
    {
        $member ??= Member::factory()->create();

        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function notification(User $user, string $type = 'loan.overdue'): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Pinjaman terlambat',
            'body' => 'Batas pengembalian sudah lewat.',
            'created_at' => now(),
        ]);
    }

    public function test_user_sees_their_own_notifications_only(): void
    {
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('user');

        $this->notification($owner);
        $this->notification($other);

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('notifications/Index')
                ->has('notifications', 1));
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = $this->userWithRole('user');
        $notification = $this->notification($user);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $owner = $this->userWithRole('user');
        $intruder = $this->userWithRole('user');
        $notification = $this->notification($owner);

        $this->actingAs($intruder)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->userWithRole('user');
        $this->notification($user);
        $this->notification($user, 'borrow_request.approved');

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_unread_count_is_shared_with_every_page(): void
    {
        $user = $this->userWithRole('user');
        $this->notification($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('unreadNotifications', 1));
    }

    public function test_approval_decision_creates_a_notification_for_the_member(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $member = Member::factory()->create();
        $borrower = $this->userWithRole('user', $member);

        $borrowRequest = BorrowRequest::factory()->create(['member_id' => $member->id]);
        BorrowRequestItem::factory()->create([
            'borrow_request_id' => $borrowRequest->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.approval.approve', $borrowRequest))
            ->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $borrower->id)
            ->where('type', 'borrow_request.approved')
            ->count());
    }

    public function test_checkout_creates_a_notification_for_the_member(): void
    {
        $member = Member::factory()->create();
        $borrower = $this->userWithRole('user', $member);
        [$loan, $reservation] = $this->reservedLoan($member);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.checkout.store', $loan), [
                'lines' => [[
                    'reservation_id' => $reservation->id,
                    'quantity' => 1,
                    'condition_out' => 'good',
                ]],
                'checklist' => [
                    'identity_verified' => true,
                    'condition_checked' => true,
                    'sop_explained' => true,
                ],
                'notes' => null,
            ])
            ->assertRedirect(route('admin.checkout.index'));

        $this->assertSame(1, Notification::where('user_id', $borrower->id)
            ->where('type', 'loan.checked_out')
            ->count());
    }

    public function test_notification_service_writes_for_users_and_members(): void
    {
        Mail::fake();

        $user = $this->userWithRole('user');

        $service = new NotificationService;

        $service->notifyUser($user, 'test.direct', 'Judul');
        $service->notifyMember($user->member_id, 'test.member', 'Judul');

        $this->assertSame(2, Notification::where('user_id', $user->id)->count());
        Mail::assertQueued(NotificationMail::class, 2);
        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($user->email));
    }

    public function test_failed_notification_does_not_abort_the_caller_transaction(): void
    {
        $user = $this->userWithRole('user');
        $service = app(NotificationService::class);

        DB::transaction(function () use ($user, $service): void {
            // 300 chars overflows varchar(255): the insert fails, the savepoint must absorb it.
            $service->notifyUser($user, str_repeat('x', 300), 'Judul');

            $user->update(['name' => 'Transaksi Masih Hidup']);
        });

        $this->assertSame('Transaksi Masih Hidup', $user->fresh()->name);
        $this->assertSame(0, Notification::count());
    }

    /**
     * @return array{0: LoanTransaction, 1: Reservation}
     */
    private function reservedLoan(Member $member): array
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);

        $borrowRequest = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
            'pickup_at' => now(),
            'return_at' => now()->addDays(2),
        ]);

        $line = BorrowRequestItem::factory()->create([
            'borrow_request_id' => $borrowRequest->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        $reservation = Reservation::factory()->create([
            'borrow_request_id' => $borrowRequest->id,
            'borrow_request_item_id' => $line->id,
            'item_id' => $item->id,
            'status' => 'active',
            'start_at' => now(),
            'end_at' => now()->addDays(2),
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $borrowRequest->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => now()->addDays(2),
        ]);

        return [$loan, $reservation];
    }
}
