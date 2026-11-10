<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use App\Models\ReturnInspection;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    /**
     * @return array{0: LoanTransaction, 1: User}
     */
    private function borrowedLoan(Item $item, int $quantity): array
    {
        $member = Member::factory()->create();
        $creator = User::factory()->create(['member_id' => $member->id]);
        $creator->roles()->attach(Role::firstOrCreate(['name' => 'user']));

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

        if ($item->tracking_method === 'serialized') {
            $assets = Asset::factory()->count($quantity)->for($item)->create(['status' => 'borrowed']);

            foreach ($assets as $asset) {
                LoanItem::factory()->create([
                    'loan_transaction_id' => $loan->id,
                    'item_id' => $item->id,
                    'asset_id' => $asset->id,
                    'quantity' => 1,
                ]);
            }
        } else {
            LoanItem::factory()->create([
                'loan_transaction_id' => $loan->id,
                'item_id' => $item->id,
                'asset_id' => null,
                'quantity' => $quantity,
            ]);
        }

        return [$loan, $creator];
    }

    private function pendingReturn(LoanTransaction $loan, User $requester): ReturnRequest
    {
        $loan->update(['status' => 'return_pending_verification']);

        return ReturnRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $requester->id,
        ]);
    }

    public function test_user_can_request_return(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 2);

        $this->actingAs($creator)
            ->post(route('borrowing.return', $loan), ['notes' => 'Dikembalikan hari ini.'])
            ->assertRedirect();

        $this->assertSame('return_pending_verification', $loan->fresh()->status);
        $this->assertSame(1, ReturnRequest::where('loan_transaction_id', $loan->id)->where('status', 'pending')->count());
        $this->assertSame(1, AuditLog::where('action', 'return.requested')->count());
    }

    public function test_other_users_cannot_request_return(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan] = $this->borrowedLoan($item, 1);

        $this->actingAs($this->userWithRole('user'))
            ->post(route('borrowing.return', $loan))
            ->assertForbidden();

        $this->assertSame('borrowed', $loan->fresh()->status);
    }

    public function test_admin_can_cancel_a_pending_return(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 1);
        $loan->update(['overdue_at' => now()->subDay()]);
        $returnRequest = $this->pendingReturn($loan, $creator);

        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.returns.cancel', $returnRequest))
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.returns.cancel', $returnRequest), ['reason' => 'Barang tidak dibawa.'])
            ->assertRedirect(route('admin.returns.index'));

        $this->assertSame('cancelled', $returnRequest->fresh()->status);
        $this->assertSame('overdue', $loan->fresh()->status);
    }

    public function test_return_verification_stores_inspection_photos(): void
    {
        Storage::fake('public');

        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 1);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                ],
                'photos' => [UploadedFile::fake()->image('kembali.jpg')],
            ])
            ->assertRedirect(route('admin.returns.index'))
            ->assertSessionHasNoErrors();

        $photos = ReturnInspection::firstOrFail()->photos;
        $this->assertCount(1, $photos);
        Storage::disk('public')->assertExists($photos[0]);
    }

    public function test_admin_verifies_a_good_return(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 2);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 2,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.returns.index'));

        $this->assertSame('completed', $loan->fresh()->status);
        $this->assertSame('verified', $returnRequest->fresh()->status);
        $this->assertSame(1, ReturnInspection::count());
        $this->assertSame(0, DamageReport::count());
        $this->assertSame(5, app(AvailabilityService::class)->summary($item)['available']);
    }

    public function test_mixed_conditions_create_damage_reports(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 3);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'damaged',
                        'quantity_returned' => 2,
                        'notes' => 'Lensa lecet.',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('completed', $loan->fresh()->status);

        $report = DamageReport::firstOrFail();
        $this->assertSame('damage', $report->type);
        $this->assertSame(2, $report->quantity);
        $this->assertSame($loan->id, $report->loan_transaction_id);
        $this->assertSame($loanItem->id, $report->loan_item_id);
        $this->assertSame($loan->member_id, $report->member_id);

        $this->assertSame(3, app(AvailabilityService::class)->summary($item)['available']);
    }

    public function test_partial_return_keeps_the_loan_open_until_everything_is_back(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 2);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('verified', $returnRequest->fresh()->status);
        $this->assertSame('borrowed', $loan->fresh()->status);
        $this->assertSame(1, $loanItem->fresh()->quantity_returned);
        $this->assertNull($loan->fresh()->completed_at);

        $second = ReturnRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_by_user_id' => $creator->id,
        ]);
        $loan->refresh()->update(['status' => 'return_pending_verification']);

        $this->actingAs($admin)
            ->post(route('admin.returns.verify', $second), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $loanItem->fresh()->quantity_returned);
        $this->assertSame('completed', $loan->fresh()->status);
    }

    public function test_partial_return_keeps_the_overdue_block_active(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 2);

        $member = Member::whereKey($loan->member_id)->firstOrFail();
        $member->update(['borrowing_status' => 'borrowing_blocked']);
        $block = MemberBorrowingBlock::factory()->create([
            'member_id' => $loan->member_id,
            'loan_transaction_id' => $loan->id,
        ]);

        $loan->update(['status' => 'overdue', 'overdue_at' => now()->subDay()]);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('overdue', $loan->fresh()->status);
        $this->assertNull($block->fresh()->ended_at);
        $this->assertSame('borrowing_blocked', $member->fresh()->borrowing_status);
    }

    public function test_return_cannot_exceed_the_remaining_quantity(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 2);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 3,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame('return_pending_verification', $loan->fresh()->status);
        $this->assertSame(0, $loanItem->fresh()->quantity_returned);
        $this->assertSame(0, ReturnInspection::count());
    }

    public function test_lost_serialized_unit_is_marked_lost(): void
    {
        $item = Item::factory()->create();
        [$loan, $creator] = $this->borrowedLoan($item, 1);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'lost',
                        'quantity_returned' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('lost', Asset::whereKey($loanItem->asset_id)->value('status'));
        $this->assertSame('loss', DamageReport::firstOrFail()->type);
        $this->assertSame('completed', $loan->fresh()->status);
    }

    public function test_good_return_keeps_unit_out_when_an_open_report_exists(): void
    {
        $item = Item::factory()->create();
        [$loan, $creator] = $this->borrowedLoan($item, 1);
        $returnRequest = $this->pendingReturn($loan, $creator);
        $loanItem = $loan->items()->firstOrFail();

        DamageReport::factory()->create([
            'item_id' => $item->id,
            'asset_id' => $loanItem->asset_id,
            'status' => 'open',
            'type' => 'damage',
        ]);

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->post(route('admin.returns.verify', $returnRequest), [
                'items' => [
                    [
                        'loan_item_id' => $loanItem->id,
                        'condition' => 'good',
                        'quantity_returned' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('maintenance', Asset::whereKey($loanItem->asset_id)->value('status'));
    }

    public function test_only_admin_can_verify_returns(): void
    {
        $item = Item::factory()->quantity()->create(['quantity_total' => 5]);
        [$loan, $creator] = $this->borrowedLoan($item, 1);
        $returnRequest = $this->pendingReturn($loan, $creator);

        $this->actingAs($creator)
            ->post(route('admin.returns.verify', $returnRequest), ['items' => []])
            ->assertForbidden();
    }
}
