<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\LoanRequest;
use App\Models\Member;
use App\Models\ReturnRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $user = $request->user();
        $isAdmin = $user->roles()->where('nama', 'ADMIN')->exists();
        $isSuper = $user->roles()->where('nama', 'SUPER_ADMIN')->exists();
        $isMember = ! $isAdmin && ! $isSuper;

        $loanQuery = LoanRequest::query()->with('user.member');
        if ($isMember) $loanQuery->where('user_id', $user->id);

        $returnQuery = ReturnRecord::query();
        if ($isMember) $returnQuery->where('diajukan_oleh', $user->id);

        $periodFrom = $request->date('from') ?? now()->startOfMonth();
        $periodTo = $request->date('to') ?? now()->endOfMonth();

        $announcements = Announcement::where('status', 'PUBLISHED')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->latest('published_at')->take(4)->get();

        $data = [
            'isAdmin' => $isAdmin, 'isSuper' => $isSuper, 'isMember' => $isMember,
            'totalInventaris' => InventoryItem::where('status', 'ACTIVE')->count(),
            'totalUnit' => InventoryUnit::count(),
            'totalPengajuan' => (clone $loanQuery)->count(),
            'pengajuanPeriode' => (clone $loanQuery)->whereBetween('created_at', [$periodFrom->copy()->startOfDay(), $periodTo->copy()->endOfDay()])->count(),
            'periodFrom' => $periodFrom, 'periodTo' => $periodTo,
            'pengajuanMenunggu' => (clone $loanQuery)->where('status', 'PENDING')->count(),
            'pengembalianMenunggu' => (clone $returnQuery)->where('status', 'SUBMITTED')->count(),
            'pengumumanTerbaru' => $announcements,
            'monthlyLoanCounts' => collect(range(5, 0))->map(function ($offset) use ($loanQuery) { $month = now()->startOfMonth()->subMonths($offset); return ['label' => $month->translatedFormat('M y'), 'total' => (clone $loanQuery)->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count()]; }),
            'overdueReturns' => (clone $loanQuery)->whereIn('status', ['BORROWED','RETURN_PENDING','RETURN_ISSUE'])->where('waktu_selesai','<',now())->count(),
            'maintenanceDue' => InventoryUnit::whereNotNull('jadwal_pemeliharaan_berikutnya')->whereDate('jadwal_pemeliharaan_berikutnya','<=',now()->addDays(14))->count(),
            'conditionSummary' => InventoryUnit::selectRaw('kondisi, COUNT(*) as total')->groupBy('kondisi')->pluck('total','kondisi'),
            'recentActivity' => AuditLog::with('actor.member')->when($isAdmin, fn($q)=>$q->where('actor_id',$user->id))->latest()->take(8)->get(),
        ];

        if ($isAdmin) {
            $data += [
                'unitTersedia' => InventoryUnit::where('status', 'AVAILABLE')->count(),
                'unitDipinjam' => InventoryUnit::where('status', 'BORROWED')->count(),
                'unitPemeliharaan' => InventoryUnit::where('status', 'MAINTENANCE')->count(),
                'auditTerbaru' => AuditLog::with('actor.member')->where('actor_id', $user->id)->latest()->take(5)->get(),
            ];
        } elseif ($isSuper) {
            $data += [
                'totalAnggota' => Member::count(),
                'anggotaAktif' => Member::where('status', 'ACTIVE')->count(),
                'auditTerbaru' => AuditLog::with('actor.member')->latest()->take(7)->get(),
            ];
        } else {
            $data += [
                'peminjamanAktif' => (clone $loanQuery)->whereIn('status', ['APPROVED', 'BORROWED', 'RETURN_SUBMITTED', 'RETURN_ISSUE'])->count(),
                'peminjamanSelesai' => (clone $loanQuery)->whereIn('status', ['COMPLETED', 'RETURNED'])->count(),
            ];
        }

        return view('dashboard', $data);
    }
}
