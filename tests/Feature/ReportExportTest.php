<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BorrowRequest;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function loan(string $reference, string $createdAt): LoanTransaction
    {
        $member = Member::factory()->create(['name' => 'Anggota '.$reference]);

        $request = BorrowRequest::factory()->approved()->create([
            'reference_no' => $reference,
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create(['member_id' => $member->id])->id,
        ]);

        $loan = LoanTransaction::factory()->create([
            'borrow_request_id' => $request->id,
            'member_id' => $member->id,
        ]);

        $loan->forceFill(['created_at' => $createdAt])->save();

        return $loan;
    }

    public function test_admin_downloads_filtered_loans_csv(): void
    {
        $this->loan('REF-DALAM', '2026-11-05 10:00:00');
        $this->loan('REF-LUAR', '2026-09-01 10:00:00');

        $response = $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('admin.reports.export', [
                'type' => 'loans',
                'from' => '2026-11-01',
                'to' => '2026-11-10',
            ]))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Referensi', $csv);
        $this->assertStringContainsString('REF-DALAM', $csv);
        $this->assertStringNotContainsString('REF-LUAR', $csv);
    }

    public function test_wm_can_download_returns_and_damage_reports(): void
    {
        $loan = $this->loan('REF-RETURN', '2026-11-05 10:00:00');

        ReturnRequest::factory()->create([
            'loan_transaction_id' => $loan->id,
            'requested_at' => '2026-11-06 10:00:00',
            'notes' => 'Kembali lengkap.',
        ]);

        $item = Item::factory()->quantity()->create(['quantity_total' => 2]);
        DamageReport::factory()->create([
            'item_id' => $item->id,
            'reported_at' => '2026-11-07 10:00:00',
            'description' => 'Lensa retak.',
        ]);

        $wm = $this->userWithRole('wm');
        $period = ['from' => '2026-11-01', 'to' => '2026-11-10'];

        $returns = $this->actingAs($wm)
            ->get(route('admin.reports.export', ['type' => 'returns'] + $period))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Kembali lengkap.', $returns);

        $damage = $this->actingAs($wm)
            ->get(route('admin.reports.export', ['type' => 'damage'] + $period))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Lensa retak.', $damage);
        $this->assertStringContainsString('Biaya', $damage);
    }

    public function test_audit_export_cannot_be_used_to_inject_spreadsheet_formulas(): void
    {
        AuditLog::create([
            'action' => '=HYPERLINK("http://evil.test")',
            'created_at' => '2026-11-08 10:00:00',
        ]);

        $csv = $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('admin.reports.export', [
                'type' => 'audit',
                'from' => '2026-11-01',
                'to' => '2026-11-10',
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_regular_user_cannot_export_or_open_the_report_page(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.reports.export', ['type' => 'loans']))
            ->assertForbidden();
    }

    public function test_report_page_shows_row_counts_for_the_period(): void
    {
        $this->loan('REF-HITUNG', '2026-11-05 10:00:00');

        $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('admin.reports.index', ['from' => '2026-11-01', 'to' => '2026-11-10']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/reports/Index')
                ->where('counts.loans', 1)
                ->where('filters.from', '2026-11-01'));
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('admin.reports.export', [
                'type' => 'loans',
                'from' => '2026-11-10',
                'to' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('from');
    }
}
