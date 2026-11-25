
@extends('layouts.app')

@section('title', 'Tambah Jenis Inventaris')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tambah Jenis Inventaris</h1>
            <p class="sub">
                Daftarkan jenis barang sebelum menambahkan unit fisiknya.
            </p>
        </div>
    </div>

    <form
        class="card"
        method="POST"
        action="{{ route('inventory-items.store') }}"
    >
        @csrf

        @include('inventory-items._form')

        <p style="margin-top: 18px">
            <button type="submit" class="btn">Simpan</button>

            <a
                class="btn secondary"
                href="{{ route('inventory-items.index') }}"
            >
                Batal
            </a>
        </p>
    </form>
@endsection
