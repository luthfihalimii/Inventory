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
        ]);
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
