@extends('layouts.app')
@section('title','Edit Jenis Inventaris')
@section('content')
<div class="page-head"><div><h1>Edit Jenis Inventaris</h1><p class="sub">Perbarui informasi jenis barang.</p></div></div>
<form class="card" method="POST" action="{{ route('inventory-items.update',$item) }}">@csrf @method('PUT')@include('inventory-items._form')<p style="margin-top:18px"><button class="btn">Simpan perubahan</button></p></form>
@endsection
