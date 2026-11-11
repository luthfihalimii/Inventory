<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wm\AccountPasswordRequest;
use App\Http\Requests\Wm\AccountRequest;
use App\Http\Requests\Wm\AccountRoleRequest;
use App\Http\Requests\Wm\AccountStatusRequest;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', User::class);

        return Inertia::render('wm/accounts/Index', [
            'accounts' => User::with(['member.division', 'roles'])
                ->orderBy('username')
                ->paginate(20),
            'members' => Member::orderBy('name')->get(['id', 'name', 'nrp']),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(AccountRequest $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', User::class);

        $member = Member::whereKey($request->validated('member_id'))->firstOrFail();

        $account = User::create([
            'member_id' => $member->id,
            'username' => $request->validated('username'),
            'name' => $member->name,
            'password' => $request->validated('password'),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $account->roles()->sync([Role::firstOrCreate(['name' => $request->validated('role')])->id]);

        $audit->record('account.created', $account, [
            'username' => $account->username,
            'member_id' => $member->id,
            'role' => $request->validated('role'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun dibuat.']);

        return back();
    }

    public function updateRole(AccountRoleRequest $request, User $account, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', User::class);

        $role = $request->validated('role');

        if ($role !== 'wm' && $this->isLastActiveWm($account)) {
            throw ValidationException::withMessages([
                'role' => 'Tidak bisa mencabut role WM dari WM aktif terakhir.',
            ]);
        }

        $before = $account->roleNames();

        $account->roles()->sync([Role::firstOrCreate(['name' => $role])->id]);

        $audit->record('account.roles_updated', $account, [], ['roles' => $before], ['roles' => [$role]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role diperbarui.']);

        return back();
    }

    public function updatePassword(
        AccountPasswordRequest $request,
        User $account,
        AuditLogger $audit,
        SessionService $sessions,
    ): RedirectResponse {
        $this->authorize('manage', User::class);

        $account->update([
            'password' => $request->validated('password'),
            'must_change_password' => true,
        ]);

        // An admin-set password must not leave the old owner's devices logged in.
        $revoked = $sessions->revokeAll($account);

        $audit->record('account.password_reset', $account, [
            'username' => $account->username,
            'sessions_revoked' => $revoked,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password direset.']);

        return back();
    }

    public function updateStatus(AccountStatusRequest $request, User $account, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', User::class);

        $status = $request->validated('status');

        if ($status === 'inactive') {
            if ($account->is($request->user())) {
                throw ValidationException::withMessages([
                    'status' => 'Tidak bisa menonaktifkan akun sendiri.',
                ]);
            }

            if ($this->isLastActiveWm($account)) {
                throw ValidationException::withMessages([
                    'status' => 'Tidak bisa menonaktifkan WM aktif terakhir.',
                ]);
            }
        }

        $before = $account->status;

        $account->update(['status' => $status]);

        if ($status === 'inactive') {
            $account->forceFill(['remember_token' => null])->saveQuietly();
            DB::table('sessions')->where('user_id', $account->id)->delete();
        }

        $audit->record('account.status_updated', $account, [], ['status' => $before], ['status' => $status]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status akun diperbarui.']);

        return back();
    }

    private function isLastActiveWm(User $account): bool
    {
        return $account->status === 'active'
            && $account->hasRole('wm')
            && ! User::where('id', '!=', $account->id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->where('name', 'wm'))
                ->exists();
    }
}
