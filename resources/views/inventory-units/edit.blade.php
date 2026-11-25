@extends('layouts.app')
@section('title','Edit Unit Inventaris')
@section('content')
<div class="page-head"><div><h1>Edit Unit Inventaris</h1><p class="sub">Perbarui informasi unit fisik.</p></div></div>
<form class="card" method="POST" action="{{ route('inventory-units.update',$unit) }}">@csrf @method('PUT')@include('inventory-units._form')<p style="margin-top:18px"><button class="btn">Simpan perubahan</button></p></form>
@endsection
