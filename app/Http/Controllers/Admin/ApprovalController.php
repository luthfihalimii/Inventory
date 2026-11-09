<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\BorrowRequest;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use App\Services\MemberEligibilityService;
use App\Services\NotificationService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('approve-borrow-request');

        $status = $request->input('status', 'pending_approval');

        return Inertia::render('admin/approval/Index', [
            'requests' => BorrowRequest::query()
                ->with(['member:id,name,nrp', 'items:id,borrow_request_id,item_id,quantity'])
                ->when(
                    in_array($status, ['pending_approval', 'approved', 'rejected', 'cancelled'], true),
                    fn ($query) => $query->where('status', $status),
                )
                ->orderByRaw("(category = 'urgent') DESC")
                ->orderBy('submitted_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => is_string($status) ? $status : ''],
        ]);
    }

    public function show(Request $request, BorrowRequest $borrowRequest, AvailabilityService $availability): Response
    {
        Gate::authorize('approve-borrow-request');

        $borrowRequest->load([
            'member:id,name,nrp,division_id,membership_status,borrowing_status',
            'member.division:id,name',
            'items.item:id,code,name,tracking_method,status,quantity_total',
            'approval.approver:id,name',
        ]);

        return Inertia::render('admin/approval/Show', [
            'request' => [
                ...$borrowRequest->only([
                    'id', 'reference_no', 'category', 'pickup_at', 'return_at',
                    'usage_location', 'purpose', 'notes', 'status', 'submitted_at',
                ]),
                'member' => [
                    ...$borrowRequest->member->only(['name', 'nrp', 'membership_status', 'borrowing_status']),
                    'division' => $borrowRequest->member->division?->name,
                ],
                'items' => $borrowRequest->items->map(fn ($line) => [
                    'item_id' => $line->item_id,
                    'code' => $line->item?->code,
                    'name' => $line->item?->name,
                    'tracking_method' => $line->item?->tracking_method,
                    'quantity' => $line->quantity,
                    'available' => $line->item
                        ? $availability->availableForPeriod($line->item, $borrowRequest->pickup_at, $borrowRequest->return_at)
                        : 0,
                ]),
                'approval' => $borrowRequest->approval?->only(['decision', 'notes', 'decided_at', 'approver_role']),
            ],
            'canDecide' => $borrowRequest->status === 'pending_approval'
                && $borrowRequest->member_id !== $request->user()->member_id,
        ]);
    }

    public function approve(
        Request $request,
        BorrowRequest $borrowRequest,
        ReservationService $reservations,
        MemberEligibilityService $eligibility,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        Gate::authorize('approve-borrow-request');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($borrowRequest, $request, $data, $reservations, $eligibility, $audit): void {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            $this->assertDecidable($locked, $request->user());
            $eligibility->assertEligible($locked->member);

            $reservations->reserve($locked);

            Approval::create([
                'borrow_request_id' => $locked->id,
                'approver_user_id' => $request->user()->id,
                'approver_member_id' => $request->user()->member_id,
                'approver_role' => $this->approverRole($request->user()),
                'decision' => 'approved',
                'notes' => $data['notes'] ?? null,
                'decided_at' => now(),
            ]);

            $locked->update(['status' => 'approved']);

            LoanTransaction::create([
                'borrow_request_id' => $locked->id,
                'member_id' => $locked->member_id,
                'status' => 'reserved',
                'due_at' => $locked->return_at,
            ]);

            $audit->record(
                'borrow_request.approved',
                $locked,
                ['reservations' => $locked->reservations()->count()],
                ['status' => 'pending_approval'],
                ['status' => 'approved'],
            );
        });

        $notifications->notifyMember(
            $borrowRequest->member_id,
            'borrow_request.approved',
            'Pengajuan disetujui',
            'Barang sudah direservasi. Silakan ambil sesuai jadwal di hari yang dipilih.',
            ['borrow_request_id' => $borrowRequest->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan disetujui.']);

        return redirect()->route('admin.approval.index');
    }

    public function reject(
        Request $request,
        BorrowRequest $borrowRequest,
        AuditLogger $audit,
        NotificationService $notifications,
    ): RedirectResponse {
        Gate::authorize('approve-borrow-request');

        $data = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ], [
            'notes.required' => 'Alasan penolakan wajib diisi.',
        ]);

        DB::transaction(function () use ($borrowRequest, $request, $data, $audit): void {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            $this->assertDecidable($locked, $request->user());

            Approval::create([
                'borrow_request_id' => $locked->id,
                'approver_user_id' => $request->user()->id,
                'approver_member_id' => $request->user()->member_id,
                'approver_role' => $this->approverRole($request->user()),
                'decision' => 'rejected',
                'notes' => $data['notes'],
                'decided_at' => now(),
            ]);

            $locked->update(['status' => 'rejected']);

            $audit->record(
                'borrow_request.rejected',
                $locked,
                ['reason' => $data['notes']],
                ['status' => 'pending_approval'],
                ['status' => 'rejected'],
            );
        });

        $notifications->notifyMember(
            $borrowRequest->member_id,
            'borrow_request.rejected',
            'Pengajuan ditolak',
            $data['notes'],
            ['borrow_request_id' => $borrowRequest->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan ditolak.']);

        return redirect()->route('admin.approval.index');
    }

    private function assertDecidable(BorrowRequest $borrowRequest, User $approver): void
    {
        if ($borrowRequest->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'decision' => 'Pengajuan sudah diputuskan.',
            ]);
        }

        if ($borrowRequest->member_id === $approver->member_id) {
            throw ValidationException::withMessages([
                'decision' => 'Tidak boleh memutuskan pengajuan sendiri.',
            ]);
        }
    }

    private function approverRole(User $approver): string
    {
        return $approver->hasRole('admin_korlab') ? 'admin_korlab' : 'wm';
    }
}
