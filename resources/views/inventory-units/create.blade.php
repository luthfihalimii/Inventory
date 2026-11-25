@extends('layouts.app')
@section('title','Tambah Unit Inventaris')
@section('content')
<div class="page-head"><div><h1>Tambah Unit Inventaris</h1><p class="sub">Satu entri mewakili satu barang fisik.</p></div></div>
<form class="card" method="POST" action="{{ route('inventory-units.store') }}">@csrf @include('inventory-units._form')<p style="margin-top:18px"><button class="btn">Simpan unit</button></p></form>
@endsection
