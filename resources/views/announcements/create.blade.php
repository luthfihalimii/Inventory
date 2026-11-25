
@extends('layouts.app')

@section('title', 'Buat Pengumuman')

@section('content')
    <div class="page-head">
        <div>
            <h1>Buat Pengumuman</h1>
            <p class="sub">Tulis informasi untuk pengguna sistem.</p>
        </div>
    </div>

    <form
        class="card"
        method="POST"
        action="{{ route('announcements.store') }}"
    >
        @csrf

        @include('announcements._form')

        <p>
            <button type="submit" class="btn">
                Simpan pengumuman
            </button>
        </p>
    </form>
@endsection
