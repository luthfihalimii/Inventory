@extends('layouts.app')
@section('title','Edit Pengumuman')
@section('content')
<div class="page-head"><div><h1>Edit Pengumuman</h1><p class="sub">Perbarui isi dan status publikasi.</p></div></div>
<form class="card" method="POST" action="{{ route('announcements.update',$announcement) }}">@csrf @method('PUT')@include('announcements._form')<p><button class="btn">Simpan perubahan</button></p></form>
@endsection
