@extends('layouts.app')
@section('title','Pengaturan Akun')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PREFERENSI & KEAMANAN</div><h1>Pengaturan akun</h1><p class="muted">Kelola kredensial dan lihat informasi keanggotaan yang terhubung.</p></div></div>
<div class="settings-grid">
<section class="panel"><div class="panel-heading"><div><h2>Informasi akun</h2><p>Data identitas dikelola oleh Webmaster.</p></div><span class="icon-tile"><i class="fa-solid fa-id-card"></i></span></div>
<div class="profile-summary"><div class="avatar">{{ strtoupper(substr($user->member?->nama ?? $user->username ?? 'E',0,1)) }}</div><div><strong>{{ $user->member?->nama ?? 'Pengelola ENT' }}</strong><span>{{ $user->roles->pluck('nama')->join(' / ') }}</span></div></div>
<dl class="detail-list"><div><dt>Kode nama</dt><dd><strong>{{ $user->member?->kode ?? '—' }}</strong> · {{ $user->member?->nama ?? '—' }}</dd></div><div><dt>NRP</dt><dd>{{ $user->member?->nrp ?? '—' }}</dd></div><div><dt>Divisi</dt><dd>{{ $user->member?->division?->nama ?? '—' }}</dd></div><div><dt>Jabatan</dt><dd>{{ $user->member?->position?->nama ?? '—' }}</dd></div><div><dt>Generasi</dt><dd>{{ $user->member?->generation?->nama ?? '—' }}</dd></div><div><dt>Status akun</dt><dd><span class="status status-success">{{ $user->status === 'ACTIVE' ? 'Aktif' : 'Nonaktif' }}</span></dd></div><div><dt>Akun dibuat</dt><dd>{{ $user->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '—' }}</dd></div><div><dt>Terakhir diperbarui</dt><dd>{{ $user->updated_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') ?? '—' }}</dd></div></dl>
</section>
<section class="panel"><div class="panel-heading"><div><h2>Kredensial</h2><p>Ubah username atau kata sandi akun.</p></div><span class="icon-tile"><i class="fa-solid fa-shield-halved"></i></span></div>
<form method="POST" action="{{ route('account.settings.update') }}" class="stack-form" data-unsaved-form>@csrf @method('PUT')
<div class="field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username',$user->username) }}" autocomplete="username" placeholder="Masukkan username"><small>Untuk akun pengelola, username digunakan saat masuk. Akun anggota tetap masuk menggunakan NRP.</small></div>
<div class="form-divider"></div><div class="field"><label for="current_password">Password saat ini</label><input id="current_password" type="password" name="current_password" autocomplete="current-password"><small>Wajib diisi jika ingin mengganti password.</small></div>
<div class="field"><label for="new_password">Password baru</label><input id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password"><small>Minimal 8 karakter.</small></div>
<div class="field"><label for="new_password_confirmation">Konfirmasi password baru</label><input id="new_password_confirmation" type="password" name="new_password_confirmation" minlength="8" autocomplete="new-password"></div>
<div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan perubahan</button></div></form>
</section>
<section class="panel span-2"><div class="panel-heading"><div><h2>Preferensi tampilan</h2><p>Pilihan tema disimpan pada perangkat dan browser ini.</p></div></div><div class="theme-options" role="group" aria-label="Pilih tema"><button type="button" class="theme-option" data-theme-choice="light"><i class="fa-regular fa-sun"></i><span>Terang</span></button><button type="button" class="theme-option" data-theme-choice="dark"><i class="fa-regular fa-moon"></i><span>Gelap</span></button><button type="button" class="theme-option" data-theme-choice="system"><i class="fa-solid fa-desktop"></i><span>Sistem</span></button></div></section>
</div>
@endsection
