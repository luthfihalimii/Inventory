<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\AuditLogger;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, SessionService $sessions): Response
    {
        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'sessions' => $sessions->forUser($request->user(), $request->session()->getId()),
        ];

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(
        PasswordUpdateRequest $request,
        SessionService $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        // A changed password must invalidate every other device, like most login systems do.
        $revoked = $sessions->revokeOthers($user, $request->session()->getId());

        $audit->record('account.password_changed', $user, ['others_revoked' => $revoked]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Revoke one of the current user's sessions.
     */
    public function destroySession(
        Request $request,
        string $session,
        SessionService $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        if ($session === $request->session()->getId()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi yang sedang dipakai tidak bisa dicabut dari daftar. Gunakan Keluar.',
            ]);
        }

        abort_unless($sessions->revoke($request->user(), $session, $request->session()->getId()), 404);

        $audit->record('session.revoked', $request->user(), ['session_id' => $session]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sesi dicabut.']);

        return back();
    }

    /**
     * Revoke every session except the current one.
     */
    public function destroyOtherSessions(
        Request $request,
        SessionService $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        $revoked = $sessions->revokeOthers($request->user(), $request->session()->getId());

        $audit->record('session.revoked_others', $request->user(), ['count' => $revoked]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $revoked === 0 ? 'Tidak ada sesi lain.' : "{$revoked} sesi lain dikeluarkan.",
        ]);

        return back();
    }
}
