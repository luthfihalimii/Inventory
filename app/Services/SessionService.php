<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /**
     * Active database sessions for a user, newest activity first.
     *
     * @return array<int, array{id: string, device: string, ip: string|null, last_active_at: string, is_current: bool}>
     */
    public function forUser(User $user, ?string $currentId): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'device' => $this->deviceLabel(is_string($row->user_agent) ? $row->user_agent : null),
                'ip' => is_string($row->ip_address) ? $row->ip_address : null,
                'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity)->toIso8601String(),
                'is_current' => $row->id === $currentId,
            ])
            ->all();
    }

    /**
     * Revoke one session the user owns. Returns false for unknown or not-owned ids.
     */
    public function revoke(User $user, string $sessionId, ?string $currentId): bool
    {
        if ($sessionId === $currentId) {
            return false;
        }

        return DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete() > 0;
    }

    /**
     * Revoke every session except the current one.
     */
    public function revokeOthers(User $user, ?string $currentId): int
    {
        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($currentId !== null) {
            $query->where('id', '!=', $currentId);
        }

        return $query->delete();
    }

    /**
     * Revoke every session for the user, including the current one.
     */
    public function revokeAll(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function deviceLabel(?string $userAgent): string
    {
        $ua = $userAgent ?? '';

        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X'), str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Perangkat tidak dikenal',
        };

        // Order matters: Edge/Opera and Chrome also advertise Safari tokens.
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser tidak dikenal',
        };

        return "{$browser} · {$os}";
    }
}
