<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->status !== 'ACTIVE' || $user->member?->status !== 'ACTIVE')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'nrp' => 'Akun atau status keanggotaan sudah tidak aktif. Silakan hubungi pengelola ENT.',
            ]);
        }

        return $next($request);
    }
}
