<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordChangeRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/ChangePassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(PasswordChangeRequest $request, AuditLogger $audit): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);

        $audit->record('auth.password_changed', $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password diperbarui.')]);

        return to_route('dashboard');
    }
}
