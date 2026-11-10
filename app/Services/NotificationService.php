<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    /**
     * Best-effort in-app notification. Never throws: a failed notification must not
     * roll back the transaction it was triggered from.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $type, string $title, ?string $body = null, array $data = []): void
    {
        try {
            // Savepoint when nested: a failed insert must not abort the caller's transaction.
            $notification = DB::transaction(fn () => Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data === [] ? null : $data,
                'created_at' => now(),
            ]));

            if ($user->email !== null && $user->email !== '') {
                // External channel: queued after commit so a rolled-back action never emails.
                Mail::to($user->email)->queue(new NotificationMail($notification));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Fan out to every account owned by the member.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyMember(int $memberId, string $type, string $title, ?string $body = null, array $data = []): void
    {
        try {
            User::where('member_id', $memberId)
                ->where('status', 'active')
                ->get()
                ->each(fn (User $user) => $this->notifyUser($user, $type, $title, $body, $data));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function unreadCountFor(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * @return Collection<int, Notification>
     */
    public function latestFor(User $user, int $limit = 50): Collection
    {
        return Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
