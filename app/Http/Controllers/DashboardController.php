<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\BorrowRequest;
use App\Models\DamageReport;
use App\Models\LoanTransaction;
use App\Models\Member;
use App\Models\MemberBorrowingBlock;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $roles = $user->roleNames();

        $stats = match (true) {
            in_array('admin_korlab', $roles, true) => $this->adminStats(),
            in_array('wm', $roles, true) => $this->wmStats(),
            default => $this->userStats($user->member_id),
        };

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'announcements' => Announcement::query()
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(['id', 'title', 'content', 'published_at']),
            ...$this->dashboardAnalytics($roles, $user->member_id),
        ]);
    }

    /**
     * @param  list<string>  $roles
     * @return array{scope: 'personal'|'all', trend: list<array{label: string, value: int}>, statusBreakdown: list<array{status: string, value: int}>}
     */
    private function dashboardAnalytics(array $roles, ?int $memberId): array
    {
        $isAdmin = in_array('admin_korlab', $roles, true) || in_array('wm', $roles, true);
        $scope = $isAdmin ? 'all' : 'personal';
        $scopedMemberId = $isAdmin ? null : $memberId;
        $start = now()->startOfMonth()->subMonths(5);

        if ($scope === 'personal' && $scopedMemberId === null) {
            $trend = [];

            for ($month = 0; $month < 6; $month++) {
                $trend[] = [
                    'label' => $start->copy()->addMonths($month)->locale('id')->isoFormat('MMM'),
                    'value' => 0,
                ];
            }

            return ['scope' => $scope, 'trend' => $trend, 'statusBreakdown' => []];
        }

        $counts = BorrowRequest::query()
            ->when($scopedMemberId, fn ($query) => $query->where('member_id', $scopedMemberId))
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $trend = [];

        for ($month = 0; $month < 6; $month++) {
            $cursor = $start->copy()->addMonths($month);
            $trend[] = [
                'label' => $cursor->locale('id')->isoFormat('MMM'),
                'value' => (int) ($counts[$cursor->format('Y-m')] ?? 0),
            ];
        }

        $statusCounts = BorrowRequest::query()
            ->when($scopedMemberId, fn ($query) => $query->where('member_id', $scopedMemberId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusBreakdown = [];

        foreach (['pending_approval', 'approved', 'rejected', 'cancelled'] as $status) {
            $value = (int) ($statusCounts[$status] ?? 0);

            if ($value > 0) {
                $statusBreakdown[] = ['status' => $status, 'value' => $value];
            }
        }

        return ['scope' => $scope, 'trend' => $trend, 'statusBreakdown' => $statusBreakdown];
    }

    /**
     * @return array<int, array{label: string, value: int, href: string|null}>
     */
    private function adminStats(): array
    {
        return [
            $this->stat('Menunggu approval', BorrowRequest::where('status', 'pending_approval')->count(), '/admin/approval'),
            $this->stat('Siap diserahkan', LoanTransaction::where('status', 'reserved')->count(), '/admin/checkout'),
            $this->stat('Menunggu verifikasi kembali', ReturnRequest::where('status', 'pending')->count(), '/admin/returns'),
            $this->stat('Laporan kerusakan terbuka', DamageReport::where('status', 'open')->count(), '/admin/inventory/damage-reports'),
            $this->stat('Pinjaman terlambat', LoanTransaction::where('status', 'overdue')->count(), null),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, href: string|null}>
     */
    private function wmStats(): array
    {
        return [
            $this->stat('Menunggu approval', BorrowRequest::where('status', 'pending_approval')->count(), '/admin/approval'),
            $this->stat('Pinjaman terlambat', LoanTransaction::where('status', 'overdue')->count(), null),
            $this->stat('Anggota terblokir', MemberBorrowingBlock::whereNull('ended_at')->count(), '/wm/blocked'),
            $this->stat('Anggota aktif', Member::where('membership_status', 'active')->count(), '/wm/members'),
            $this->stat('Laporan kerusakan terbuka', DamageReport::where('status', 'open')->count(), '/admin/inventory/damage-reports'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, href: string|null}>
     */
    private function userStats(?int $memberId): array
    {
        if ($memberId === null) {
            return [];
        }

        return [
            $this->stat('Pengajuan diproses', BorrowRequest::where('member_id', $memberId)->where('status', 'pending_approval')->count(), '/peminjaman'),
            $this->stat('Pinjaman aktif', LoanTransaction::where('member_id', $memberId)->whereIn('status', ['borrowed', 'return_pending_verification'])->count(), '/peminjaman'),
            $this->stat('Terlambat', LoanTransaction::where('member_id', $memberId)->where('status', 'overdue')->count(), '/peminjaman'),
            $this->stat('Riwayat selesai', LoanTransaction::where('member_id', $memberId)->where('status', 'completed')->count(), '/peminjaman'),
        ];
    }

    /**
     * @return array{label: string, value: int, href: string|null}
     */
    private function stat(string $label, int $value, ?string $href): array
    {
        return ['label' => $label, 'value' => $value, 'href' => $href];
    }
}
