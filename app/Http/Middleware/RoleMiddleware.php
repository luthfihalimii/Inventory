<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        abort_unless(
            $user->status === 'ACTIVE' && $user->member?->status === 'ACTIVE',
            403,
            'Akun atau keanggotaan tidak aktif.'
        );

        $hasRole = $user->roles()->whereIn('nama', $roles)->exists();
        abort_unless($hasRole, 403, 'Anda tidak memiliki akses.');

        return $next($request);
    }
}
