<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\BorrowRequest;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\LoanTransaction;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use App\Models\Reservation;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixtures for Playwright. Never part of DatabaseSeeder.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $division = Division::where('code', 'RED')->firstOrFail();
        $userRole = Role::where('name', 'user')->firstOrFail();

        $quantityItem = Item::updateOrCreate(
            ['code' => 'E2E-KAMERA'],
            [
                'name' => 'E2E Kamera',
                'category_id' => Category::query()->value('id'),
                'location_id' => Location::query()->value('id'),
                'tracking_method' => 'quantity',
                'quantity_total' => 10,
                'status' => 'active',
                'qr_token' => 'e2e-kamera-token',
            ],
        );

        $serializedItem = Item::updateOrCreate(
            ['code' => 'E2E-TRIPOD'],
            [
                'name' => 'E2E Tripod',
                'category_id' => Category::query()->value('id'),
                'location_id' => Location::query()->value('id'),
                'tracking_method' => 'serialized',
                'quantity_total' => null,
                'status' => 'active',
                'qr_token' => 'e2e-tripod-token',
            ],
        );

        Asset::updateOrCreate(
            ['asset_code' => 'E2E-AST-1'],
            ['item_id' => $serializedItem->id, 'qr_token' => 'e2e-tripod-asset-1', 'condition' => 'good', 'status' => 'available'],
        );

        // Serialized loan awaiting checkout, for the wrong-QR scan scenario.
        $reservedMember = $this->member('2410000005', 'MBR-0005', 'Anggota Checkout E2E', $division);
        $reservedRequest = BorrowRequest::updateOrCreate(
            ['reference_no' => 'REQ-E2E-000001'],
            [
                'member_id' => $reservedMember->id,
                'created_by_user_id' => $this->account('2410000005', 'Anggota Checkout E2E', $reservedMember, $userRole)->id,
                'category' => 'scheduled',
                'pickup_at' => now()->subHour(),
                'return_at' => now()->addDays(2),
                'usage_location' => 'Lab Jurnalistik',
                'status' => 'approved',
                'submitted_at' => now()->subDay(),
            ],
        );
        $reservedLine = $reservedRequest->items()->firstOrCreate(['item_id' => $serializedItem->id], ['quantity' => 1]);
        $reservedAsset = Asset::where('asset_code', 'E2E-AST-1')->firstOrFail();
        Reservation::updateOrCreate(
            ['borrow_request_id' => $reservedRequest->id, 'asset_id' => $reservedAsset->id],
            [
                'borrow_request_item_id' => $reservedLine->id,
                'item_id' => $serializedItem->id,
                'quantity' => 1,
                'quantity_fulfilled' => 0,
                'start_at' => $reservedRequest->pickup_at,
                'end_at' => $reservedRequest->return_at,
                'status' => 'active',
            ],
        );
        LoanTransaction::updateOrCreate(
            ['borrow_request_id' => $reservedRequest->id],
            ['member_id' => $reservedMember->id, 'status' => 'reserved', 'due_at' => $reservedRequest->return_at],
        );

        // Overdue member for the block scenario.
        $blockedMember = $this->member('2410000009', 'MBR-0009', 'Anggota Terlambat E2E', $division, 'borrowing_blocked');
        $blockedAccount = $this->account('2410000009', 'Anggota Terlambat E2E', $blockedMember, $userRole);
        $blockedRequest = BorrowRequest::updateOrCreate(
            ['reference_no' => 'REQ-E2E-000009'],
            [
                'member_id' => $blockedMember->id,
                'created_by_user_id' => $blockedAccount->id,
                'category' => 'scheduled',
                'pickup_at' => now()->subDays(5),
                'return_at' => now()->subDay(),
                'usage_location' => 'Lab Jurnalistik',
                'status' => 'approved',
                'submitted_at' => now()->subDays(6),
            ],
        );
        $blockedLoan = LoanTransaction::updateOrCreate(
            ['borrow_request_id' => $blockedRequest->id],
            [
                'member_id' => $blockedMember->id,
                'status' => 'overdue',
                'checked_out_at' => now()->subDays(5),
                'due_at' => now()->subDay(),
                'overdue_at' => now()->subHours(12),
            ],
        );
        LoanItem::firstOrCreate([
            'loan_transaction_id' => $blockedLoan->id,
            'item_id' => $quantityItem->id,
        ], ['asset_id' => null, 'quantity' => 1]);
        MemberBorrowingBlock::firstOrCreate(
            ['loan_transaction_id' => $blockedLoan->id, 'ended_at' => null],
            [
                'member_id' => $blockedMember->id,
                'reason' => 'Terlambat mengembalikan barang.',
                'started_at' => now()->subHours(12),
            ],
        );

        // Pending damaged return for the verification scenario.
        $returnMember = $this->member('2410000006', 'MBR-0006', 'Anggota Kembali E2E', $division);
        $returnAccount = $this->account('2410000006', 'Anggota Kembali E2E', $returnMember, $userRole);
        $returnRequest = BorrowRequest::updateOrCreate(
            ['reference_no' => 'REQ-E2E-000006'],
            [
                'member_id' => $returnMember->id,
                'created_by_user_id' => $returnAccount->id,
                'category' => 'scheduled',
                'pickup_at' => now()->subDays(4),
                'return_at' => now()->addDay(),
                'usage_location' => 'Lab Jurnalistik',
                'status' => 'approved',
                'submitted_at' => now()->subDays(5),
            ],
        );
        $returnLine = $returnRequest->items()->firstOrCreate(['item_id' => $quantityItem->id], ['quantity' => 2]);
        $returnLoan = LoanTransaction::updateOrCreate(
            ['borrow_request_id' => $returnRequest->id],
            [
                'member_id' => $returnMember->id,
                'status' => 'return_pending_verification',
                'checked_out_at' => now()->subDays(4),
                'due_at' => $returnRequest->return_at,
            ],
        );
        LoanItem::firstOrCreate(
            ['loan_transaction_id' => $returnLoan->id, 'item_id' => $quantityItem->id],
            ['asset_id' => null, 'quantity' => 2],
        );
        ReturnRequest::firstOrCreate(
            ['loan_transaction_id' => $returnLoan->id, 'status' => 'pending'],
            ['requested_by_user_id' => $returnAccount->id, 'requested_at' => now(), 'notes' => 'E2E rusak.'],
        );
    }

    private function member(string $nrp, string $code, string $name, Division $division, string $borrowingStatus = 'eligible'): Member
    {
        return Member::updateOrCreate(
            ['nrp' => $nrp],
            [
                'member_code' => $code,
                'name' => $name,
                'division_id' => $division->id,
                'membership_status' => 'active',
                'borrowing_status' => $borrowingStatus,
            ],
        );
    }

    private function account(string $username, string $name, Member $member, Role $role): User
    {
        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'member_id' => $member->id,
                'name' => $name,
                'email' => $username.'@ent.local',
                'password' => Hash::make('password'),
                'status' => 'active',
                'must_change_password' => false,
            ],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
