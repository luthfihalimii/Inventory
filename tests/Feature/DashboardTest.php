<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public static function staffRoles(): array
    {
        return [['admin_korlab'], ['wm']];
    }

    #[DataProvider('staffRoles')]
    public function test_admin_and_wm_dashboards_aggregate_all_scopes_and_exclude_old_records(string $roleName): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => $roleName]));

        BorrowRequest::factory()->create([
            'status' => 'pending_approval',
            'created_at' => now()->startOfMonth()->addDay(),
        ]);
        BorrowRequest::factory()->create([
            'status' => 'approved',
            'created_at' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDay(),
        ]);
        BorrowRequest::factory()->create([
            'status' => 'rejected',
            'created_at' => now()->subMonthsNoOverflow(5)->startOfMonth(),
        ]);
        BorrowRequest::factory()->create([
            'status' => 'cancelled',
            'created_at' => now()->subMonthsNoOverflow(6)->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('scope', 'all')
                ->where('trend.0.value', 1)
                ->where('trend.3.value', 1)
                ->where('trend.5.value', 1)
                ->where('trend', fn ($months) => collect($months)->sum('value') === 3)
                ->where('statusBreakdown', fn ($statuses) => collect($statuses)
                    ->mapWithKeys(fn (array $status) => [$status['status'] => $status['value']])
                    ->all() === [
                        'pending_approval' => 1,
                        'approved' => 1,
                        'rejected' => 1,
                        'cancelled' => 1,
                    ])
            );
    }

    public function test_dashboard_excludes_a_record_immediately_before_the_six_month_boundary(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create(['name' => 'admin_korlab']));

        BorrowRequest::factory()->create([
            'status' => 'approved',
            'created_at' => now()->startOfMonth()->subMonthsNoOverflow(5),
        ]);
        BorrowRequest::factory()->create([
            'status' => 'rejected',
            'created_at' => now()->startOfMonth()->subMonthsNoOverflow(5)->subSecond(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'all')
                ->where('trend.0.value', 1)
                ->where('trend', fn ($months) => collect($months)->sum('value') === 1)
                ->where('statusBreakdown', fn ($statuses) => collect($statuses)
                    ->mapWithKeys(fn (array $status) => [$status['status'] => $status['value']])
                    ->all() === ['approved' => 1, 'rejected' => 1])
            );
    }

    public function test_dashboard_exposes_member_scoped_trend_and_status_data(): void
    {
        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id]);

        BorrowRequest::factory()->for($member)->create([
            'status' => 'pending_approval',
            'created_at' => now()->startOfMonth(),
        ]);
        BorrowRequest::factory()->for($member)->create([
            'status' => 'approved',
            'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
        ]);
        BorrowRequest::factory()->create([
            'status' => 'approved',
            'created_at' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('scope', 'personal')
                ->has('trend', 6)
                ->where('trend.4.value', 1)
                ->where('trend.5.value', 1)
                ->has('statusBreakdown', 2)
                ->where('statusBreakdown.0.status', 'pending_approval')
                ->where('statusBreakdown.0.value', 1)
                ->where('statusBreakdown.1.status', 'approved')
                ->where('statusBreakdown.1.value', 1)
            );
    }
}
