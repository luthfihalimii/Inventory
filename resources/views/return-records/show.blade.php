@extends('layouts.app')
@section('title','Detail Pengembalian')
@section('content')
<div class="page-heading"><div><div class="eyebrow">RETURN INSPECTION</div><h1>Detail pengembalian</h1><p class="muted">Detail barang yang dikembalikan dan hasil pemeriksaan.</p></div></div>
<section class="panel">
    <dl class="detail-list">
        <div><dt>Kode peminjaman</dt><dd>{{ $return->loanRequest?->kode_pengajuan }}</dd></div>
        <div><dt>Pengaju</dt><dd>{{ $return->applicant?->nama }}</dd></div>
        <div><dt>Status pengembalian</dt><dd>{{ $return->status }}</dd></div>
        <div><dt>Waktu pengembalian diminta</dt><dd>{{ $return->waktu_pengembalian_diminta?->format('d/m/Y H:i') ?? '—' }} WIB</dd></div>
        <div><dt>Pengajuan dikirim</dt><dd>{{ $return->waktu_pengajuan?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt>Catatan pengguna</dt><dd>{{ $return->catatan_pengguna ?? '—' }}</dd></div>
    </dl>
</section>
<section class="panel"><h2>Foto pengembalian</h2>
    @forelse($return->photos as $photo)
        <a class="btn btn-secondary btn-sm" href="{{ route('return-photos.show',$photo) }}" target="_blank" rel="noopener">Lihat foto {{ $loop->iteration }}</a>
    @empty
        <p class="muted">Tidak ada foto terlampir.</p>
    @endforelse
</section>
@if(auth()->user()->roles()->where('nama','ADMIN')->exists())
<section class="panel"><h2>Pemeriksaan unit</h2>
    <form method="POST" action="{{ route('return-records.verify',$return) }}">@csrf
        <div class="table-wrap"><table><thead><tr><th>Unit</th><th>Kondisi hasil pemeriksaan</th><th>Catatan</th></tr></thead><tbody>
        @foreach($return->loanRequest->details as $detail)
            @foreach($detail->allocatedUnits as $allocation)
                <tr><td>{{ $allocation->inventoryUnit?->kode_unit }}</td><td><select name="units[{{ $allocation->inventory_unit_id }}][kondisi]"><option value="GOOD">Baik</option><option value="MINOR_DAMAGE">Rusak ringan</option><option value="DAMAGED">Rusak</option></select></td><td><input name="units[{{ $allocation->inventory_unit_id }}][catatan]"></td></tr>
            @endforeach
        @endforeach
        </tbody></table></div>
        <div class="field" style="margin:14px 0"><label>Catatan Admin</label><textarea name="catatan_admin"></textarea></div>
        <div class="field" style="margin:14px 0"><label>Hasil verifikasi</label><select name="hasil"><option value="VERIFIED">Terverifikasi</option><option value="ISSUE_FOUND">Ditemukan masalah</option></select></div>
        <button class="btn btn-primary" type="submit">Simpan verifikasi</button>
    </form>
</section>
@endif
@endsection
