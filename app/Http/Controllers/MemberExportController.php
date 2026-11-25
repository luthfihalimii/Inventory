<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class MemberExportController extends Controller
{
    public function export(Request $request): StreamedResponse|View
    {
        abort_unless($request->user()->roles()->where('nama','SUPER_ADMIN')->exists(),403);
        if ($request->query('format') === 'pdf') {
            $members = Member::with(['division','position','generation'])
                ->leftJoin('generations as g', 'members.generasi_id', '=', 'g.id')
                ->leftJoin('divisions as d', 'members.divisi_id', '=', 'd.id')
                ->select('members.*')->orderBy('g.nama')->orderBy('d.nama')->orderBy('members.nama')->get();
            $rows = $members->map(fn ($m) => [$m->kode, $m->nrp, $m->nama, $m->generation?->nama, $m->division?->nama, $m->position?->nama, $m->status === 'ACTIVE' ? 'Aktif' : 'Nonaktif']);
            return view('reports.print', ['reportTitle' => 'Direktori Anggota ENT', 'subtitle' => 'Daftar anggota diurutkan berdasarkan generasi, divisi, lalu nama.',
                'columns' => ['Kode', 'NRP', 'Nama', 'Generasi', 'Divisi', 'Jabatan', 'Status'], 'rows' => $rows,
                'summary' => ['Jumlah anggota' => $members->count(), 'Aktif' => $members->where('status','ACTIVE')->count()]]);
        }
        return response()->streamDownload(function(){
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            \App\Support\CsvSafety::write($out,['kode','nrp','nama','divisi','jabatan','generasi','status']);
            Member::with(['division','position','generation'])->leftJoin('generations as g', 'members.generasi_id', '=', 'g.id')->leftJoin('divisions as d', 'members.divisi_id', '=', 'd.id')->select('members.*')->orderBy('g.nama')->orderBy('d.nama')->orderBy('members.nama')->chunk(500,function($members)use($out){foreach($members as $m)\App\Support\CsvSafety::write($out,[$m->kode,$m->nrp,$m->nama,$m->division?->nama,$m->position?->nama,$m->generation?->nama,$m->status]);});
            fclose($out);
        },'ent-members-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
