@extends('layouts.app')
@section('title','Masuk')
@section('content')
<div class="auth-page">
  <section class="auth-visual"><a class="auth-brand" href="https://ent.pens.ac.id/" target="_blank" rel="noopener noreferrer"><img src="{{ asset('images/ent-logo.svg') }}" alt="Logo ENT"><span><strong>EEPIS News & Network Team</strong><span>Politeknik Elektronika Negeri Surabaya</span></span></a><div class="auth-copy"><div class="eyebrow" style="color:#65D3D0">INTERNAL WORKSPACE</div><h1>Inventaris ENT,<br>lebih tertata.</h1><p>Satu ruang kerja untuk mengelola inventaris, peminjaman, pengembalian, dan aktivitas operasional ENT.</p></div><div style="font-size:11px;color:#AFC7D9;position:relative;z-index:1">© {{ date('Y') }} EEPIS News & Network Team</div></section>
  <section class="auth-side"><div class="auth-card"><img class="brand-mark" src="{{ asset('images/ent-logo.svg') }}" alt="ENT"><div class="eyebrow" style="margin-top:22px">SELAMAT DATANG KEMBALI</div><h2>Masuk ke workspace</h2><p>Gunakan NRP untuk akun anggota atau username untuk akun pengelola.</p>
  <form method="POST" action="{{ route('login.store') }}" class="stack-form">@csrf
    @if($errors->any())
      <div class="alert error" role="alert"><strong>Gagal masuk.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="field"><label for="identity">NRP / Username</label><input id="identity" name="identity" value="{{ old('identity') }}" required autofocus autocomplete="username" placeholder="Masukkan NRP atau username"></div>
    <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password"></div>
    <label style="display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted)"><input type="checkbox" name="remember" value="1" style="width:15px;min-height:15px"> Ingat sesi saya</label>
    <button class="btn btn-primary" style="width:100%;min-height:44px" type="submit">Masuk <i class="fa-solid fa-arrow-right"></i></button>
  </form><div class="forgot-note"><strong><i class="fa-solid fa-circle-info"></i> Lupa password?</strong><br>Hubungi atau kunjungi divisi Webmaster ENT untuk bantuan pengaturan ulang password. Reset mandiri melalui email/OTP tidak tersedia.</div><div class="auth-foot">Akses terbatas untuk anggota dan pengelola ENT.<br>Dengan masuk, gunakan akun sesuai kewenangan masing-masing.</div></div></section>
</div>
@endsection
