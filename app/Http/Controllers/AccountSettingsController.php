<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()->load(['member.division', 'member.position', 'member.generation', 'roles'])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isManager = $user->roles()->whereIn('nama', ['ADMIN', 'SUPER_ADMIN'])->exists();
        $usernameRules = [$isManager ? 'required' : 'nullable', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9._-]{2,59}$/', Rule::unique('users', 'username')->ignore($user->id)];
        $data = $request->validate([
            'username' => $usernameRules,
            'current_password' => ['required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'username.unique' => 'Username sudah digunakan akun lain.',
            'username.regex' => 'Username harus diawali huruf dan hanya boleh menggunakan huruf, angka, titik, garis bawah, atau tanda hubung.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'new_password.min' => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $old = ['username' => $user->username];
        if (array_key_exists('username', $data)) {
            $user->username = $data['username'] ?: null;
        }
        if (! empty($data['new_password'])) {
            $user->password = $data['new_password'];
            $user->must_change_password = false;
        }
        $user->save();
        $this->writeAudit('UPDATE_ACCOUNT', 'User', $user->id, $old, ['username' => $user->username, 'password' => '[UPDATED]']);

        return back()->with('success', 'Pengaturan akun berhasil diperbarui.');
    }
}
