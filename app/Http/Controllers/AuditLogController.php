<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;

class AuditLogController extends Controller
{
    private function queryFor(Request $request)
    {
        $query = AuditLog::with(['actor.member', 'managerTerm.member', 'managerTerm.role'])->latest();
        if (! $request->user()->roles()->where('nama', 'SUPER_ADMIN')->exists()) $query->where('actor_id', $request->user()->id);
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));
        if ($request->filled('aksi')) $query->where('aksi', $request->string('aksi')->toString());
        return $query;
    }

    public function index(Request $request): View
    {
        Validator::make($request->all(), ['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']])->validate();
        return view('audit-logs.index', ['logs' => $this->queryFor($request)->paginate(25)->withQueryString()]);
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        Validator::make($request->all(), ['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']])->validate();
        if ($request->query('format') === 'pdf') {
            $logs = $this->queryFor($request)->get();
            $rows = $logs->map(fn ($log) => [
                $log->created_at?->format('d/m/Y H:i:s'),
                $log->actor?->member?->nama ?? $log->actor?->username ?? 'Sistem',
                str_replace('_', ' ', $log->aksi), $log->jenis_entitas, $log->entity_id ?? '—',
                $log->managerTerm?->role?->nama ?? '—',
            ]);
            return response()->view('reports.print', ['reportTitle' => 'Laporan Audit Log',
                'subtitle' => 'Catatan aktivitas dan perubahan data sesuai hak akses serta filter yang dipilih.',
                'columns' => ['Waktu', 'Pelaku', 'Aksi', 'Jenis entitas', 'ID entitas', 'Masa jabatan'],
                'rows' => $rows, 'summary' => ['Jumlah aktivitas' => $logs->count()]]);
        }
        return response()->streamDownload(function () use ($request) {
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            \App\Support\CsvSafety::write($out,['waktu','pelaku','aksi','jenis_entitas','entity_id','manager_term_id']);
            $this->queryFor($request)->chunk(500,function($logs)use($out){foreach($logs as $log)\App\Support\CsvSafety::write($out,[$log->created_at?->format('Y-m-d H:i:s'),$log->actor?->member?->nama ?? $log->actor?->username ?? 'Sistem',$log->aksi,$log->jenis_entitas,$log->entity_id,$log->manager_term_id]);});
            fclose($out);
        },'ent-audit-log-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
