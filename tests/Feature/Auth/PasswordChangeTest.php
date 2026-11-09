<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_flagged_must_change_password_are_redirected(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_change_password_page_is_reachable_without_redirect_loop(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get(route('password.change'))->assertOk();
    }

    public function test_password_can_be_changed_and_flag_is_cleared(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($user)->put(route('password.change.store'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertSame(1, AuditLog::where('action', 'auth.password_changed')->count());
    }

    public function test_wrong_current_password_keeps_the_flag(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($user)->put(route('password.change.store'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_users_without_the_flag_are_not_redirected(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
