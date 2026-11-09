<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'admin_korlab']));

        return $user;
    }

    /**
     * @return array{0: LoanTransaction, 1: BorrowRequest}
     */
    private function approvedLoan(Item $item, int $quantity): array
    {
        $member = Member::factory()->create();
        $creator = User::factory()->create(['member_id' => $member->id]);

        $request = BorrowRequest::factory()->approved()->create([
            'member_id' => $member->id,
            'created_by_user_id' => $creator->id,
            'pickup_at' => now()->addHour(),
            'return_at' => now()->addDays(3),
        ]);

        $line = $request->items()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        if ($item->tracking_method === 'serialized') {
            $assets = Asset::factory()->count($quantity)->for($item)->create(['status' => 'available']);

            foreach ($assets as $asset) {
                Reservation::factory()->create([
                    'borrow_request_id' => $request->id,
                    'borrow_request_item_id' => $line->id,
                    'item_id' => $item->id,
                    'asset_id' => $asset->id,
                    'quantity' => 1,
                    'start_at' => $request->pickup_at,
                    'end_at' => $request->return_at,
                ]);
            }
        } else {
            Reservation::factory()->create([
                'borrow_request_id' => $request->id,
                'borrow_request_item_id' => $line->id,
                'item_id' => $item->id,
                'asset_id' => null,
                'quantity' => $quantity,
                'start_at' => $request->pickup_at,
                'end_at' => $request->return_at,
            ]);
        }

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
            'status' => 'reserved',
            'due_at' => $request->return_at,
        ]);

        return [$loan, $request];
    }

    public function test_admin_can_check_out_quantity_items(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan] = $this->approvedLoan($item, 2);
        $reservation = $loan->borrowRequest->reservations()->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.store', $loan), [
                'lines' => [
                    [
                        'reservation_id' => $reservation->id,
                        'quantity' => 2,
                        'condition_out' => 'good',
                    ],
                ],
                'checklist' => ['identity_verified' => true],
            ])
            ->assertRedirect(route('admin.checkout.index'));

        $this->assertSame('borrowed', $loan->fresh()->status);
        $this->assertNotNull($loan->fresh()->checked_out_at);

        $loanItem = LoanItem::where('loan_transaction_id', $loan->id)->firstOrFail();
        $this->assertSame(2, $loanItem->quantity);
        $this->assertNull($loanItem->asset_id);

        $this->assertSame('fulfilled', $reservation->fresh()->status);
        $this->assertSame(2, $reservation->fresh()->quantity_fulfilled);
        $this->assertSame(1, $loan->handover()->count());
        $this->assertSame(1, AuditLog::where('action', 'loan.checked_out')->count());

        $this->assertSame(3, app(AvailabilityService::class)->summary($item)['available']);
    }

    public function test_admin_can_check_out_serialized_units(): void
    {
        $item = Item::factory()->create();
        [$loan] = $this->approvedLoan($item, 2);
        $reservations = $loan->borrowRequest->reservations()->orderBy('id')->get();

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.store', $loan), [
                'lines' => $reservations->map(fn ($reservation) => [
                    'reservation_id' => $reservation->id,
                    'asset_id' => $reservation->asset_id,
                    'quantity' => 1,
                    'condition_out' => 'good',
                ])->all(),
            ])
            ->assertRedirect(route('admin.checkout.index'));

        $this->assertSame(2, LoanItem::where('loan_transaction_id', $loan->id)->count());
        $this->assertSame(2, Asset::where('status', 'borrowed')->count());
    }

    public function test_scan_only_validates_tokens(): void
    {
        $item = Item::factory()->create();
        $asset = Asset::factory()->for($item)->create(['status' => 'available']);
        [$loan] = $this->approvedLoan($item, 1);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.checkout.scan', $loan), ['token' => $asset->qr_token])
            ->assertOk()
            ->assertJson(['valid' => true, 'type' => 'asset']);

        $this->assertSame('available', $asset->fresh()->status);

        $other = Asset::factory()->create(['status' => 'available']);

        $this->actingAs($admin)
            ->postJson(route('admin.checkout.scan', $loan), ['token' => $other->qr_token])
            ->assertStatus(422)
            ->assertJson(['valid' => false]);

        $this->actingAs($admin)
            ->postJson(route('admin.checkout.scan', $loan), ['token' => 'unknown-token'])
            ->assertStatus(422);
    }

    public function test_partial_checkout_requires_reason_and_releases_omitted_units(): void
    {
        $item = Item::factory()->create();
        [$loan] = $this->approvedLoan($item, 2);
        $reservations = $loan->borrowRequest->reservations()->orderBy('id')->get();
        $admin = $this->admin();

        $payload = [
            'lines' => [[
                'reservation_id' => $reservations[0]->id,
                'asset_id' => $reservations[0]->asset_id,
                'quantity' => 1,
                'condition_out' => 'good',
            ]],
        ];

        $this->actingAs($admin)
            ->post(route('admin.checkout.store', $loan), $payload)
            ->assertSessionHasErrors('reason');

        $this->assertSame('reserved', $loan->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.checkout.store', $loan), [...$payload, 'reason' => 'Satu unit dipakai kegiatan lain.'])
            ->assertRedirect();

        $this->assertSame('released', $reservations[1]->fresh()->status);
        $this->assertSame(1, LoanItem::where('loan_transaction_id', $loan->id)->count());
    }

    public function test_unit_swap_moves_the_reservation_to_the_new_asset(): void
    {
        $item = Item::factory()->create();
        $replacement = Asset::factory()->for($item)->create(['status' => 'available']);
        [$loan] = $this->approvedLoan($item, 1);
        $reservation = $loan->borrowRequest->reservations()->firstOrFail();
        $original = Asset::whereKey($reservation->asset_id)->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.store', $loan), [
                'lines' => [[
                    'reservation_id' => $reservation->id,
                    'asset_id' => $replacement->id,
                    'quantity' => 1,
                    'condition_out' => 'good',
                ]],
                'reason' => 'Unit asli rusak saat pengambilan.',
            ])
            ->assertRedirect();

        $this->assertSame($replacement->id, $reservation->fresh()->asset_id);
        $this->assertSame('borrowed', $replacement->fresh()->status);
        $this->assertSame('available', $original->fresh()->status);
    }

    public function test_only_admin_can_check_out(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan] = $this->approvedLoan($item, 1);

        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'user']));

        $this->actingAs($user)
            ->post(route('admin.checkout.store', $loan), ['lines' => []])
            ->assertForbidden();
    }

    public function test_transaction_must_still_be_reserved(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan] = $this->approvedLoan($item, 1);
        $loan->update(['status' => 'borrowed']);
        $reservation = $loan->borrowRequest->reservations()->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.checkout.store', $loan), [
                'lines' => [[
                    'reservation_id' => $reservation->id,
                    'quantity' => 1,
                    'condition_out' => 'good',
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, LoanItem::count());
    }
}
