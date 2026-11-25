@extends('layouts.app')
@section('title','Detail Anggota')
@section('content')
<div class="page-head"><div><h1>Detail Anggota</h1><p class="sub">Identitas, status, akun, dan riwayat jabatan anggota.</p></div></div>
<section class="card"><h2>{{ $member->nama }}</h2><p><strong>Kode:</strong> {{ $member->kode ?? '—' }}</p><p><strong>NRP:</strong> {{ $member->nrp ?? '—' }}</p><p><strong>Divisi:</strong> {{ $member->division?->nama ?? '—' }}</p><p><strong>Jabatan:</strong> {{ $member->position?->nama ?? '—' }}</p><p><strong>Generasi:</strong> {{ $member->generation?->nama ?? '—' }}</p><p><strong>Status:</strong> {{ $member->status }}</p><a class="btn" href="{{ route('members.edit',$member) }}">Edit anggota</a></section><section class="card"><h2>Akun terhubung</h2>@foreach($member->accounts as $account)<p>{{ $account->username }} · {{ $account->roles->pluck('nama')->join(', ') }} · {{ $account->status }}</p>@endforeach</section>
@endsection
