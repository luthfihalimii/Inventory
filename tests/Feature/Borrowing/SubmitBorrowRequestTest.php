<?php

namespace Tests\Feature\Borrowing;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\SopAcknowledgement;
use App\Models\SopDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitBorrowRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SopDocument::factory()->active()->create();
    }

    private function userWithRole(string $role, ?Member $member = null): User
    {
        $member ??= Member::factory()->create();

        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Item $item, array $overrides = []): array
    {
        return [
            'category' => 'scheduled',
            'pickup_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'return_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'usage_location' => 'Lab Jurnalistik',
            'purpose' => null,
            'notes' => null,
            'sop_accepted' => true,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            ...$overrides,
        ];
    }

    public function test_user_can_submit_a_borrow_request(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->post(route('borrowing.store'), $this->payload($item))
            ->assertRedirect();

        $request = BorrowRequest::firstOrFail();

        $this->assertSame('pending_approval', $request->status);
        $this->assertSame($user->member_id, $request->member_id);
        $this->assertSame($user->id, $request->created_by_user_id);
        $this->assertMatchesRegularExpression('/^REQ-\d{8}-\d{6}$/', $request->reference_no);
        $this->assertSame(1, $request->items()->count());
        $this->assertSame(1, SopAcknowledgement::where('borrow_request_id', $request->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'borrow_request.submitted')->count());
    }

    public function test_scheduled_requests_require_two_days_lead_time(): void
    {
        $item = Item::factory()->quantity()->create();

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.store'), $this->payload($item, [
                'pickup_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('pickup_at');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_urgent_requests_skip_lead_time_but_require_a_purpose(): void
    {
        $item = Item::factory()->quantity()->create();
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->post(route('borrowing.store'), $this->payload($item, [
                'category' => 'urgent',
                'pickup_at' => now()->addHours(6)->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('purpose');

        $this->actingAs($user)
            ->post(route('borrowing.store'), $this->payload($item, [
                'category' => 'urgent',
                'pickup_at' => now()->addHours(6)->format('Y-m-d H:i:s'),
                'purpose' => 'Liputan mendesak.',
            ]))
            ->assertRedirect();

        $this->assertSame(1, BorrowRequest::where('category', 'urgent')->count());
    }

    public function test_inactive_or_blocked_members_cannot_submit(): void
    {
        $item = Item::factory()->quantity()->create();

        $this->actingAs($this->userWithRole('user', Member::factory()->inactive()->create()))
            ->post(route('borrowing.store'), $this->payload($item))
            ->assertSessionHasErrors('member');

        $this->actingAs($this->userWithRole('user', Member::factory()->borrowingBlocked()->create()))
            ->post(route('borrowing.store'), $this->payload($item))
            ->assertSessionHasErrors('member');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_inactive_items_cannot_be_requested(): void
    {
        $item = Item::factory()->quantity()->inactive()->create(['quantity_total' => 5]);

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.store'), $this->payload($item))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_quantity_cannot_exceed_available_stock(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.store'), $this->payload($item, [
                'items' => [['item_id' => $item->id, 'quantity' => 3]],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_duplicate_items_and_invalid_dates_are_rejected(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->post(route('borrowing.store'), $this->payload($item, [
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 1],
                    ['item_id' => $item->id, 'quantity' => 1],
                ],
            ]))
            ->assertSessionHasErrors('items.0.item_id');

        $this->actingAs($user)
            ->post(route('borrowing.store'), $this->payload($item, [
                'return_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('return_at');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_sop_must_be_accepted(): void
    {
        $item = Item::factory()->quantity()->create();

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.store'), $this->payload($item, ['sop_accepted' => false]))
            ->assertSessionHasErrors('sop_accepted');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_only_user_role_can_submit_requests(): void
    {
        $item = Item::factory()->quantity()->create();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('borrowing.store'), $this->payload($item))
            ->assertForbidden();

        $this->assertSame(0, BorrowRequest::count());
    }
}
