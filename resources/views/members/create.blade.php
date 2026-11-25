@extends('layouts.app')
@section('title', 'Tambah Anggota')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">MANAJEMEN KEANGGOTAAN</div><h1>Tambah anggota</h1><p class="muted">Pilih jenis data yang akan dibuat. Akun pengelola memerlukan konfirmasi tambahan.</p></div>
</div>
<form class="panel stack-form" method="POST" action="{{ route('members.store') }}" id="member-create-form">
    @csrf
    @include('members._form')

    <div class="form-divider"></div>
    <div class="field">
        <label for="account-kind">Jenis akun yang dibuat</label>
        <select id="account-kind" name="accounts[0][role]" required>
            <option value="USER" @selected(old('accounts.0.role', 'USER') === 'USER')>Anggota biasa (USER)</option>
            <option value="ADMIN" @selected(old('accounts.0.role') === 'ADMIN')>Administrator (ADMIN / Korlab)</option>
            <option value="SUPER_ADMIN" @selected(old('accounts.0.role') === 'SUPER_ADMIN')>Super Administrator (SUPER_ADMIN / WM)</option>
        </select>
        <small>Anggota biasa menggunakan NRP. Akun pengelola menggunakan username.</small>
    </div>

    <div id="manager-account-fields" class="panel" style="display:none;background:var(--surface-2)">
        <h2>Konfirmasi akun pengelola</h2>
        <p class="muted">Akun ADMIN dan SUPER_ADMIN memiliki kewenangan berbeda dari akun anggota biasa. Pastikan penetapan role dan pemegang akun sudah disetujui.</p>
        <div class="form-grid">
            <div class="field"><label for="manager-username">Username pengelola</label><input id="manager-username" name="accounts[0][username]" value="{{ old('accounts.0.username') }}" autocomplete="off"></div>
            <div class="field"><label for="manager-password">Password awal</label><input id="manager-password" type="password" name="accounts[0][password]" minlength="8" autocomplete="new-password"></div>
        </div>
        <label style="display:flex;align-items:flex-start;gap:9px;margin-top:14px">
            <input type="checkbox" name="manager_confirmation" value="1" id="manager-confirmation" @checked(old('manager_confirmation') === '1')>
            <span>Saya mengonfirmasi bahwa akun pengelola ini memang ditetapkan untuk anggota tersebut dan kewenangannya telah disetujui.</span>
        </label>
        @error('manager_confirmation')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div id="user-account-fields" class="panel" style="background:var(--surface-2)">
        <h2>Akun anggota</h2>
        <div class="field"><label for="user-password">Password awal</label><input id="user-password" type="password" name="accounts[0][password]" minlength="8" autocomplete="new-password" required></div>
        <p class="muted" style="margin-bottom:0">Akun USER masuk menggunakan NRP dan diwajibkan mengganti password awal saat pertama kali masuk.</p>
    </div>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Simpan anggota</button>
        <a class="btn btn-secondary" href="{{ route('members.index') }}">Batal</a>
    </div>
</form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const role = document.getElementById('account-kind');
    const manager = document.getElementById('manager-account-fields');
    const user = document.getElementById('user-account-fields');
    const managerPass = document.getElementById('manager-password');
    const userPass = document.getElementById('user-password');
    const username = document.getElementById('manager-username');
    const confirmation = document.getElementById('manager-confirmation');
    function sync() {
        const isManager = role.value !== 'USER';
        manager.style.display = isManager ? '' : 'none';
        user.style.display = isManager ? 'none' : '';
        managerPass.required = isManager;
        username.required = isManager;
        confirmation.required = isManager;
        userPass.required = !isManager;
        managerPass.disabled = !isManager;
        username.disabled = !isManager;
        confirmation.disabled = !isManager;
        userPass.disabled = isManager;
    }
    role.addEventListener('change', sync);
    sync();
});
</script>
@endpush
@endsection
