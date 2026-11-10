<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.test']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_the_password_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.test']);
        $token = null;

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-Password-123',
            'password_confirmation' => 'New-Password-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New-Password-123', $user->fresh()->password));
    }

    public function test_invalid_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $original = $user->password;

        $this->post('/reset-password', [
            'token' => 'token-palsu',
            'email' => $user->email,
            'password' => 'New-Password-123',
            'password_confirmation' => 'New-Password-123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($original, $user->fresh()->password);
    }
}
