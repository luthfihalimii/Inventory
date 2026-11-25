<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        // `nrp` tetap diterima untuk kompatibilitas dengan form login lama.
        // Form baru dapat memakai `identity` untuk NRP maupun username.
        $identity = substr(trim((string) $request->input(
            'identity',
            $request->input('nrp', $request->input('username', ''))
        )), 0, 120);

        $throttleKey = 'login:'.hash('sha256', Str::lower($identity).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['nrp' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.']);
        }

        if ($identity === '' || ! is_string($request->input('password'))) {
            throw ValidationException::withMessages([
                'nrp' => 'NRP/username dan password wajib diisi.',
            ]);
        }

        $user = null;

        if (ctype_digit($identity)) {
            // NRP hanya digunakan untuk akun USER.
            $user = User::with(['member', 'roles'])
                ->whereHas('member', fn ($query) => $query->where('nrp', $identity))
                ->whereHas('roles', fn ($query) => $query->where('nama', 'USER'))
                ->first();
        } else {
            // Username hanya digunakan untuk akun pengelola.
            $user = User::with(['member', 'roles'])
                ->where('username', $identity)
                ->whereHas('roles', fn ($query) => $query->whereIn('nama', ['ADMIN', 'SUPER_ADMIN']))
                ->first();
        }

        if (
            ! $user
            || ! Hash::check($request->input('password'), $user->password)
            || $user->status !== 'ACTIVE'
            || $user->member?->status !== 'ACTIVE'
        ) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'nrp' => 'NRP/username atau password tidak sesuai, atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $isUserAccount = $user->roles()->where('nama', 'USER')->exists();
        Auth::login($user, $isUserAccount && $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
