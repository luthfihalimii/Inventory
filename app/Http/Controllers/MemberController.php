<?php

namespace App\Http\Controllers;

use App\Models\ManagerTerm;
use App\Models\Member;
use App\Models\Division;
use App\Models\Generation;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Member::with(['division', 'position', 'generation', 'accounts.roles']);
        if ($request->filled('q')) $query->where(fn($q)=>$q->where('members.nama','like','%'.$request->q.'%')->orWhere('members.nrp','like','%'.$request->q.'%')->orWhere('members.kode','like','%'.$request->q.'%'));
        if ($request->filled('divisi_id')) $query->where('members.divisi_id',$request->divisi_id);
        if ($request->filled('generasi_id')) $query->where('members.generasi_id',$request->generasi_id);
        if ($request->filled('status')) $query->where('members.status',$request->status);
        return view('members.index', ['divisions' => Division::orderBy('nama')->get(), 'generations' => Generation::orderBy('nama')->get(), 'members'=>$query
            ->leftJoin('generations as member_generations', 'members.generasi_id', '=', 'member_generations.id')
            ->leftJoin('divisions as member_divisions', 'members.divisi_id', '=', 'member_divisions.id')
            ->select('members.*')
            ->orderBy('member_generations.nama')
            ->orderBy('member_divisions.nama')
            ->orderBy('members.nama')
            ->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        return view('members.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:members,kode'],
            'nrp' => ['required', 'string', 'max:30', 'unique:members,nrp'],
            'nama' => ['required', 'string', 'max:150'],
            'divisi_id' => ['required', Rule::exists('divisions', 'id')->where('status_aktif', true)],
            'jabatan_id' => ['nullable', 'exists:positions,id'],
            'generasi_id' => ['required', 'exists:generations,id'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*.role' => ['required', 'string', Rule::in(['USER', 'ADMIN', 'SUPER_ADMIN']), 'distinct'],
            'accounts.*.username' => ['nullable', 'string', 'max:60', 'distinct', 'unique:users,username'],
            'accounts.*.password' => ['required', 'string', 'min:8'],
            'manager_confirmation' => ['nullable', 'accepted'],
        ]);

        $this->validateAccountIdentifiers($data['accounts']);
        if (collect($data['accounts'])->contains(fn ($account) => in_array($account['role'] ?? '', ['ADMIN', 'SUPER_ADMIN'], true))
            && ($data['manager_confirmation'] ?? null) !== '1') {
            throw ValidationException::withMessages([
                'manager_confirmation' => 'Konfirmasi khusus akun pengelola wajib disetujui.',
            ]);
        }
        $positionId = $data['jabatan_id'] ?: $this->noPositionId();

        $member = DB::transaction(function () use ($data, $positionId, $request) {
            $member = Member::create([
                'kode' => $data['kode'],
                'nrp' => $data['nrp'],
                'nama' => $data['nama'],
                'divisi_id' => $data['divisi_id'],
                'jabatan_id' => $positionId,
                'generasi_id' => $data['generasi_id'],
                'status' => $data['status'],
            ]);

            foreach ($data['accounts'] as $accountData) {
                $this->createAccountForMember($member, $accountData, $request->user());
            }

            return $member;
        });

        $this->writeAudit('CREATE', 'Member', $member->id, null, [
            ...$member->only(['kode', 'nrp', 'nama', 'divisi_id', 'jabatan_id', 'generasi_id', 'status']),
            'account_roles' => collect($data['accounts'])->pluck('role')->all(),
        ]);

        return redirect()->route('members.index')->with('success', 'Anggota dan akun berhasil ditambahkan.');
    }

    public function show(Member $member): View
    {
        return view('members.show', [
            'member' => $member->load(['division', 'position', 'generation', 'accounts.roles', 'managerTerms.role']),
        ]);
    }

    public function edit(Member $member): View
    {
        return view('members.edit', [
            'member' => $member->load(['accounts.roles']),
            ...$this->formOptions(),
        ]);
    }

    /** Update identitas anggota. Akun baru ditambahkan melalui new_accounts[]. */
    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('members', 'kode')->ignore($member->id)],
            'nrp' => ['required', 'string', 'max:30', Rule::unique('members', 'nrp')->ignore($member->id)],
            'nama' => ['required', 'string', 'max:150'],
            'divisi_id' => ['required', Rule::exists('divisions', 'id')->where('status_aktif', true)],
            'jabatan_id' => ['nullable', 'exists:positions,id'],
            'generasi_id' => ['required', 'exists:generations,id'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'new_accounts' => ['sometimes', 'array'],
            'new_accounts.*.role' => ['required_with:new_accounts', 'string', Rule::in(['USER', 'ADMIN', 'SUPER_ADMIN']), 'distinct'],
            'new_accounts.*.username' => ['nullable', 'string', 'max:60', 'distinct', 'unique:users,username'],
            'new_accounts.*.password' => ['required_with:new_accounts', 'string', 'min:8'],
        ]);

        $this->validateAccountIdentifiers($data['new_accounts'] ?? []);
        $old = $member->only(['kode', 'nrp', 'nama', 'divisi_id', 'jabatan_id', 'generasi_id', 'status']);
        $positionId = $data['jabatan_id'] ?: $this->noPositionId();

        DB::transaction(function () use ($request, $member, $data, $positionId) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'kode' => $data['kode'],
                'nrp' => $data['nrp'],
                'nama' => $data['nama'],
                'divisi_id' => $data['divisi_id'],
                'jabatan_id' => $positionId,
                'generasi_id' => $data['generasi_id'],
                'status' => $data['status'],
            ]);

            foreach ($data['new_accounts'] ?? [] as $accountData) {
                $this->createAccountForMember($locked, $accountData, $request->user());
            }

            if ($data['status'] === 'INACTIVE') {
                $this->assertMemberCanBeDeactivated($locked, $request->user());
                $accountIds = $locked->accounts()->pluck('id');
                foreach ($locked->accounts()->get() as $account) {
                    $account->update([
                        'status' => 'INACTIVE',
                        'remember_token' => Str::random(60),
                    ]);
                }
                DB::table('sessions')->whereIn('user_id', $accountIds)->delete();
                ManagerTerm::whereIn('user_id', $accountIds)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now(), 'updated_at' => now()]);
            } elseif ($data['status'] === 'ACTIVE') {
                // Keanggotaan yang aktif kembali memulihkan akun USER. Akun pengelola
                // tetap nonaktif sampai masa jabatan baru ditetapkan melalui rotasi akun.
                $locked->accounts()
                    ->whereHas('roles', fn (Builder $query) => $query->where('nama', 'USER'))
                    ->update(['status' => 'ACTIVE']);
            }
        });

        $fresh = $member->fresh();
        $this->writeAudit('UPDATE', 'Member', $member->id, $old, [
            ...$fresh->only(['kode', 'nrp', 'nama', 'divisi_id', 'jabatan_id', 'generasi_id', 'status']),
        ]);

        return redirect()->route('members.index')->with('success', 'Data anggota berhasil diperbarui.');
    }

    public function deactivate(Member $member): RedirectResponse
    {
        $old = $member->only(['status']);

        DB::transaction(function () use ($member) {
            $locked = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->assertMemberCanBeDeactivated($locked, request()->user());
            $accountIds = $locked->accounts()->pluck('id');

            $locked->update(['status' => 'INACTIVE']);
            foreach ($locked->accounts()->get() as $account) {
                $account->update([
                    'status' => 'INACTIVE',
                    'remember_token' => Str::random(60),
                ]);
            }

            DB::table('sessions')->whereIn('user_id', $accountIds)->delete();
            ManagerTerm::whereIn('user_id', $accountIds)
                ->whereNull('ended_at')
                ->update(['ended_at' => now(), 'updated_at' => now()]);
        });

        $this->writeAudit('DEACTIVATE', 'Member', $member->id, $old, ['status' => 'INACTIVE']);

        return back()->with('success', 'Anggota dan seluruh akun yang terhubung dinonaktifkan.');
    }

    /**
     * Dipanggil saat pergantian Webmaster/Korlab.
     * Akun sistem tetap sama, tetapi pemegang akun, password, dan catatan masa jabatan berubah.
     * Route/form untuk operasi ini sengaja belum ditambahkan karena Views dan Routes ditunda.
     */
    public function rotateManagerAccount(Request $request, User $account): RedirectResponse
    {
        abort_unless($request->user()->roles()->where('nama', 'SUPER_ADMIN')->exists(), 403);
        abort_unless($account->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists(), 422);

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldMemberId = $account->member_id;
        $oldTermId = null;

        DB::transaction(function () use ($request, $account, $data, &$oldTermId) {
            $lockedAccount = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $newMember = Member::whereKey($data['member_id'])->lockForUpdate()->firstOrFail();

            if ($newMember->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'member_id' => 'Pemegang akun harus merupakan anggota aktif.',
                ]);
            }

            $role = $lockedAccount->roles()->firstOrFail();
            $currentTerm = ManagerTerm::where('user_id', $lockedAccount->id)
                ->whereNull('ended_at')->lockForUpdate()->first();
            $oldTermId = $currentTerm?->id;

            if ($currentTerm) {
                $currentTerm->update(['ended_at' => now()]);
            }

            $lockedAccount->update([
                'member_id' => $newMember->id,
                'password' => $data['password'],
                'status' => 'ACTIVE',
                'remember_token' => Str::random(60),
            ]);

            ManagerTerm::create([
                'user_id' => $lockedAccount->id,
                'member_id' => $newMember->id,
                'role_id' => $role->id,
                'started_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            DB::table('sessions')->where('user_id', $lockedAccount->id)->delete();
        });

        $this->writeAudit('ROTATE_MANAGER_ACCOUNT', 'User', $account->id, [
            'member_id' => $oldMemberId,
            'manager_term_id' => $oldTermId,
        ], [
            'member_id' => $data['member_id'],
            'password_changed' => true,
            'sessions_revoked' => true,
        ]);

        if ($request->user()->id === $account->id) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('success', 'Password dan masa jabatan diperbarui. Silakan login kembali.');
        }

        return back()->with('success', 'Pemegang akun dan password diperbarui. Sesi lama telah dicabut; akun harus login kembali.');
    }

    private function assertMemberCanBeDeactivated(Member $member, ?User $actor): void
    {
        if ($actor && (int) $actor->member_id === (int) $member->id) {
            throw ValidationException::withMessages([
                'status' => 'Anggota yang sedang digunakan oleh akun pengelola tidak dapat dinonaktifkan sebelum akun pengelola dialihkan ke anggota pengganti.',
            ]);
        }

        $hasManagerAccount = $member->accounts()
            ->where('status', 'ACTIVE')
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('nama', ['ADMIN', 'SUPER_ADMIN']))
            ->exists();

        if ($hasManagerAccount) {
            throw ValidationException::withMessages([
                'status' => 'Alihkan akun ADMIN/SUPER_ADMIN ke pemegang baru terlebih dahulu sebelum menonaktifkan anggota ini.',
            ]);
        }
    }

    private function formOptions(): array
    {
        return [
            'divisions' => \App\Models\Division::where('status_aktif', true)->orderBy('nama')->get(),
            'positions' => Position::orderByRaw("CASE nama
                WHEN 'Ketua' THEN 1 WHEN 'Sekretaris 1' THEN 2 WHEN 'Sekretaris 2' THEN 3
                WHEN 'Bendahara' THEN 4 WHEN 'Koordinator LAB' THEN 5
                WHEN 'Koordinator PSDM' THEN 6 WHEN 'PSDM' THEN 7 WHEN 'Redaksi' THEN 8
                WHEN 'PIC Webmaster' THEN 9 WHEN 'PIC Reporter' THEN 10
                WHEN 'PIC Videografer' THEN 11 WHEN 'PIC Fotografer' THEN 12
                WHEN 'PIC Desain Grafis' THEN 13 WHEN 'PIC Copywriter' THEN 14
                WHEN 'PIC Illustrator' THEN 15 WHEN 'PIC Perencanaan Konten' THEN 16
                WHEN 'Tidak Ada' THEN 99 ELSE 50 END")->get(),
            'generations' => \App\Models\Generation::orderBy('nama')->get(),
            'roles' => Role::orderBy('nama')->get(),
        ];
    }

    private function noPositionId(): int
    {
        $id = Position::where('nama', 'Tidak Ada')->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'jabatan_id' => 'Data jabatan "Tidak Ada" belum tersedia. Jalankan PositionSeeder terlebih dahulu.',
            ]);
        }

        return (int) $id;
    }

    private function validateAccountIdentifiers(array $accounts): void
    {
        $seen = [];

        foreach ($accounts as $index => $account) {
            $role = $account['role'] ?? null;
            if (in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) {
                $username = trim((string) ($account['username'] ?? ''));
                if ($username === '' || ctype_digit($username) || ! preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $username)) {
                    throw ValidationException::withMessages([
                        "accounts.{$index}.username" => 'Username pengelola harus diawali huruf dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
                    ]);
                }
            }

            if (isset($seen[$role])) {
                throw ValidationException::withMessages([
                    "accounts.{$index}.role" => 'Satu anggota tidak boleh memiliki dua akun dengan role yang sama.',
                ]);
            }
            $seen[$role] = true;
        }
    }

    private function createAccountForMember(Member $member, array $data, User $actor): User
    {
        $role = Role::where('nama', $data['role'])->lockForUpdate()->firstOrFail();

        if (in_array($data['role'], ['ADMIN', 'SUPER_ADMIN'], true)) {
            if ($member->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'accounts' => 'Akun pengelola hanya dapat diberikan kepada anggota aktif.',
                ]);
            }

            $alreadyAssigned = User::whereHas('roles', fn (Builder $query) => $query->where('roles.id', $role->id))
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'accounts' => "Akun {$data['role']} sudah tersedia. Maksimal hanya satu akun untuk role tersebut.",
                ]);
            }
        }

        if ($data['role'] === 'USER' && $member->accounts()->whereHas('roles', fn (Builder $query) => $query->where('nama', 'USER'))->exists()) {
            throw ValidationException::withMessages([
                'accounts' => 'Anggota ini sudah memiliki akun USER.',
            ]);
        }

        $user = User::create([
            'member_id' => $member->id,
            'username' => in_array($data['role'], ['ADMIN', 'SUPER_ADMIN'], true) ? trim($data['username']) : null,
            'password' => $data['password'],
            'status' => $member->status === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE',
            'must_change_password' => $data['role'] === 'USER',
        ]);
        $user->roles()->sync([$role->id]);
        if ($data['role'] === 'USER') {
            $user->notify(new \App\Notifications\LoanRequestNotification('Ganti password awal', 'Password awal akun perlu diganti melalui Pengaturan Akun.', 'PASSWORD_CHANGE_REQUIRED'));
        }

        if (in_array($data['role'], ['ADMIN', 'SUPER_ADMIN'], true)) {
            ManagerTerm::create([
                'user_id' => $user->id,
                'member_id' => $member->id,
                'role_id' => $role->id,
                'started_at' => now(),
                'ended_at' => $member->status === 'ACTIVE' ? null : now(),
                'created_by' => $actor->id,
            ]);
        }

        $this->writeAudit('CREATE_ACCOUNT', 'User', $user->id, null, [
            'member_id' => $member->id,
            'role' => $role->nama,
            'username' => $user->username,
            'status' => $user->status,
        ]);

        return $user;
    }
}
