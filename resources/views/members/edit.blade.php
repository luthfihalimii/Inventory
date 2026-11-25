@extends('layouts.app')
@section('title','Edit Anggota')
@section('content')
<div class="page-head"><div><h1>Edit Anggota</h1><p class="sub">Perbarui identitas keanggotaan.</p></div></div>
<form class="card" method="POST" action="{{ route('members.update',$member) }}">@csrf @method('PUT')@include('members._form')
<p class="hint">Akun yang sudah terhubung tidak diubah melalui formulir identitas ini.</p><p><button class="btn">Simpan perubahan</button></p></form>
@endsection
