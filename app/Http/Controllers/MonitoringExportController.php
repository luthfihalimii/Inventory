<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\LoanRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringExportController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->roles()->whereIn('nama',['ADMIN','SUPER_ADMIN'])->exists(),403);
    }

    public function inventory(Request $request): StreamedResponse|View
    {
        $this->authorizeManager($request);
        if ($request->query('format') === 'pdf') {
            $items = InventoryItem::with(['category', 'units'])->orderBy('kode_item')->get();
            $rows = $items->map(function ($item) {
                $units = $item->units;
                return [$item->kode_item, $item->nama, $item->category?->nama ?? '—', $item->status,
                    $units->count(), $units->where('status','AVAILABLE')->count(),
                    $units->where('status','RESERVED')->count(), $units->where('status','BORROWED')->count(),
                    $units->where('status','MAINTENANCE')->count()];
            });
            return view('reports.print', ['reportTitle' => 'Laporan Inventaris', 'subtitle' => 'Ringkasan jenis barang dan kondisi unit inventaris.',
                'columns' => ['Kode', 'Nama barang', 'Kategori', 'Status', 'Total unit', 'Tersedia', 'Reservasi', 'Dipinjam', 'Pemeliharaan'],
                'rows' => $rows, 'summary' => ['Jenis barang' => $items->count(), 'Total unit' => $items->sum(fn ($i) => $i->units->count())]]);
        }
        return response()->streamDownload(function(){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            \App\Support\CsvSafety::write($out,['kode_item','nama_item','kategori','status_item','jumlah_unit','tersedia','direservasi','dipinjam','pemeliharaan']);
            InventoryItem::with(['category','units'])->orderBy('kode_item')->chunk(250,function($items)use($out){foreach($items as $item){$units=$item->units;\App\Support\CsvSafety::write($out,[$item->kode_item,$item->nama,$item->category?->nama,$item->status,$units->count(),$units->where('status','AVAILABLE')->count(),$units->where('status','RESERVED')->count(),$units->where('status','BORROWED')->count(),$units->where('status','MAINTENANCE')->count()]);}});
            fclose($out);
        },'ent-inventory-report-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function loans(Request $request): StreamedResponse|View
    {
        $this->authorizeManager($request);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'status' => ['nullable', 'string', 'max:40']]);
        $query=LoanRequest::with(['user.member','details.inventoryItem'])->latest();
        if($request->filled('from'))$query->whereDate('created_at','>=',$request->date('from'));
        if($request->filled('to'))$query->whereDate('created_at','<=',$request->date('to'));
        if($request->filled('status'))$query->where('status',$request->status);
        if ($request->query('format') === 'pdf') {
            $loans = (clone $query)->get();
            $rows = $loans->flatMap(fn ($loan) => $loan->details->map(fn ($detail) => [
                $loan->kode_pengajuan, $loan->user?->member?->nama ?? $loan->user?->username ?? '—',
                $loan->user?->member?->nrp ?? '—', $loan->kategori === 'URGENT' ? 'Mendadak' : 'Terjadwal',
                $loan->waktu_mulai?->format('d/m/Y H:i'), $loan->waktu_selesai?->format('d/m/Y H:i'),
                str_replace('_', ' ', $loan->status), $detail->inventoryItem?->nama ?? '—', $detail->jumlah,
            ]));
            return view('reports.print', ['reportTitle' => 'Laporan Riwayat Peminjaman',
                'subtitle' => 'Riwayat transaksi peminjaman sesuai filter yang dipilih.',
                'columns' => ['Kode pengajuan', 'Pemohon', 'NRP', 'Kategori', 'Mulai', 'Selesai', 'Status', 'Barang', 'Jumlah'],
                'rows' => $rows, 'summary' => ['Jumlah pengajuan' => $loans->count(), 'Jumlah detail barang' => $rows->count()]]);
        }
        return response()->streamDownload(function()use($query){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            \App\Support\CsvSafety::write($out,['kode_pengajuan','nama_pemohon','nrp','kategori','waktu_mulai','waktu_selesai','status','item','jumlah']);
            $query->chunk(250,function($loans)use($out){foreach($loans as $loan){foreach($loan->details as $detail){\App\Support\CsvSafety::write($out,[$loan->kode_pengajuan,$loan->user?->member?->nama,$loan->user?->member?->nrp,$loan->kategori,$loan->waktu_mulai?->format('Y-m-d H:i:s'),$loan->waktu_selesai?->format('Y-m-d H:i:s'),$loan->status,$detail->inventoryItem?->nama,$detail->jumlah]);}}});
            fclose($out);
        },'ent-loan-history-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
