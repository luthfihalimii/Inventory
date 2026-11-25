<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function writeAudit(string $action, string $entity, ?int $entityId, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(),
            'manager_term_id' => auth()->user()?->activeManagerTerm()?->id,
            'aksi' => $action,
            'jenis_entitas' => $entity,
            'entity_id' => $entityId,
            'old_value' => $this->safeAuditData($old),
            'new_value' => $this->safeAuditData($new),
        ]);
    }

    protected function notifyUsersWithRole(string $role, \Illuminate\Notifications\Notification $notification): void
    {
        \App\Models\User::query()
            ->where('status', 'ACTIVE')
            ->whereHas('member', fn ($query) => $query->where('status', 'ACTIVE'))
            ->whereHas('roles', fn ($query) => $query->where('nama', $role))
            ->get()
            ->each(fn ($user) => $user->notify($notification));
    }

    private function safeAuditData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        unset($data['password'], $data['password_confirmation'], $data['remember_token']);

        return $data;
    }
}
