<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['member_id' => Member::factory()->create()->id]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    public function test_admin_can_create_a_draft_announcement(): void
    {
        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Pemeliharaan lab',
                'content' => 'Lab tutup hari Sabtu.',
            ])
            ->assertRedirect();

        $announcement = Announcement::firstOrFail();

        $this->assertSame('draft', $announcement->status);
        $this->assertNull($announcement->published_at);
        $this->assertSame($admin->id, $announcement->created_by_user_id);
        $this->assertSame(1, AuditLog::where('action', 'announcement.created')->count());
    }

    public function test_wm_and_members_cannot_manage_announcements(): void
    {
        $this->actingAs($this->userWithRole('wm'))
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('user'))
            ->post(route('admin.announcements.store'), [
                'title' => 'Palsu',
                'content' => 'Palsu',
            ])
            ->assertForbidden();

        $this->assertSame(0, Announcement::count());
    }

    public function test_admin_can_publish_and_unpublish_an_announcement(): void
    {
        $admin = $this->userWithRole('admin_korlab');
        $announcement = Announcement::factory()->create(['created_by_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->put(route('admin.announcements.status', $announcement), ['status' => 'published'])
            ->assertRedirect();

        $announcement->refresh();
        $this->assertSame('published', $announcement->status);
        $this->assertNotNull($announcement->published_at);
        $this->assertSame(1, AuditLog::where('action', 'announcement.published')->count());

        $this->actingAs($admin)
            ->put(route('admin.announcements.status', $announcement), ['status' => 'draft'])
            ->assertRedirect();

        $this->assertSame('draft', $announcement->fresh()->status);
    }

    public function test_admin_can_update_an_announcement(): void
    {
        $admin = $this->userWithRole('admin_korlab');
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
            'title' => 'Lama',
            'content' => 'Isi lama',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.announcements.update', $announcement), [
                'title' => 'Baru',
                'content' => 'Isi baru',
            ])
            ->assertRedirect();

        $announcement->refresh();
        $this->assertSame('Baru', $announcement->title);
        $this->assertSame($admin->id, $announcement->updated_by_user_id);
        $this->assertSame(1, AuditLog::where('action', 'announcement.updated')->count());
    }

    public function test_dashboard_only_shows_published_announcements(): void
    {
        $admin = $this->userWithRole('admin_korlab');

        Announcement::factory()->published()->create([
            'created_by_user_id' => $admin->id,
            'title' => 'Terbit',
        ]);
        Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
            'title' => 'Draft rahasia',
        ]);

        $this->actingAs($this->userWithRole('user'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('announcements', 1)
                ->where('announcements.0.title', 'Terbit'));
    }

    public function test_wm_blocked_member_list_separates_active_and_released_blocks(): void
    {
        $member = Member::factory()->create(['borrowing_status' => 'borrowing_blocked']);

        MemberBorrowingBlock::factory()->create([
            'member_id' => $member->id,
            'loan_transaction_id' => LoanTransaction::factory()->create(['member_id' => $member->id])->id,
        ]);
        MemberBorrowingBlock::factory()->released()->create([
            'member_id' => Member::factory()->create()->id,
            'loan_transaction_id' => LoanTransaction::factory()->create()->id,
        ]);

        $this->actingAs($this->userWithRole('wm'))
            ->get(route('wm.blocked.index', ['status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('wm/blocked/Index')
                ->has('blocks.data', 1)
                ->where('filters.status', 'active'));

        $this->actingAs($this->userWithRole('wm'))
            ->get(route('wm.blocked.index', ['status' => 'released']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('blocks.data', 1));

        $this->actingAs($this->userWithRole('wm'))
            ->get(route('wm.blocked.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('blocks.data', 2));
    }

    public function test_only_wm_can_open_the_blocked_member_list(): void
    {
        $this->actingAs($this->userWithRole('admin_korlab'))
            ->get(route('wm.blocked.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('user'))
            ->get(route('wm.blocked.index'))
            ->assertForbidden();
    }

    public function test_dashboard_statistics_use_role_specific_aggregates(): void
    {
        $admin = $this->userWithRole('admin_korlab');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('stats', 5)
                ->where('stats.0.label', 'Menunggu approval'));

        $this->actingAs($this->userWithRole('wm'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('stats', 5)
                ->where('stats.2.label', 'Anggota terblokir'));

        $this->actingAs($this->userWithRole('user'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('stats', 4)
                ->where('stats.0.label', 'Pengajuan diproses'));
    }
}
