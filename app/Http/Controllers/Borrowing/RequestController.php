<?php

namespace App\Http\Controllers\Borrowing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Borrowing\StoreBorrowRequestRequest;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\Member;
use App\Models\SopAcknowledgement;
use App\Models\SopDocument;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use App\Services\MemberEligibilityService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequestController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('borrowing/Index', [
            'requests' => BorrowRequest::query()
                ->where('created_by_user_id', $request->user()->id)
                ->withCount('items')
                ->latest('submitted_at')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, MemberEligibilityService $eligibility): Response
    {
        $sop = SopDocument::where('is_active', true)->firstOrFail();
        $member = $request->user()->member;

        return Inertia::render('borrowing/Create', [
            'sop' => $sop->only(['id', 'version', 'title', 'content']),
            // §7.1: show why borrowing is blocked instead of failing on submit.
            'blocks' => $member ? $eligibility->activeBlockSummary($member) : [],
        ]);
    }

    public function store(
        StoreBorrowRequestRequest $request,
        MemberEligibilityService $eligibility,
        AvailabilityService $availability,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();
        $member = $user->member;

        if ($member === null) {
            abort(403);
        }

        $eligibility->assertEligible($member);

        $data = $request->validated();
        $pickup = Carbon::parse($data['pickup_at']);
        $return = Carbon::parse($data['return_at']);
        $sop = SopDocument::where('is_active', true)->firstOrFail();

        $borrowRequest = DB::transaction(function () use ($data, $user, $member, $sop, $pickup, $return, $eligibility, $availability, $audit): BorrowRequest {
            $member = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $eligibility->assertEligible($member);

            $borrowRequest = BorrowRequest::create([
                'member_id' => $member->id,
                'created_by_user_id' => $user->id,
                'category' => $data['category'],
                'pickup_at' => $pickup,
                'return_at' => $return,
                'usage_location' => $data['usage_location'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending_approval',
                'submitted_at' => now(),
            ]);

            foreach ($data['items'] as $line) {
                $item = Item::whereKey($line['item_id'])->firstOrFail();

                if ($item->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => "Barang {$item->name} sedang tidak aktif.",
                    ]);
                }

                $available = $availability->availableForPeriod($item, $pickup, $return);

                if ($available < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$item->name} tidak cukup untuk periode ini (tersedia {$available}).",
                    ]);
                }

                $borrowRequest->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $borrowRequest->update([
                'reference_no' => sprintf('REQ-%s-%06d', now()->format('Ymd'), $borrowRequest->id),
            ]);

            SopAcknowledgement::create([
                'member_id' => $member->id,
                'borrow_request_id' => $borrowRequest->id,
                'sop_document_id' => $sop->id,
                'accepted_at' => now(),
                'ip_address' => request()->ip(),
            ]);

            $audit->record('borrow_request.submitted', $borrowRequest, [
                'reference_no' => $borrowRequest->reference_no,
                'category' => $borrowRequest->category,
                'item_count' => count($data['items']),
            ]);

            return $borrowRequest;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan terkirim.']);

        return redirect()->route('borrowing.show', $borrowRequest);
    }

    public function show(Request $request, BorrowRequest $borrowRequest): Response
    {
        abort_unless($borrowRequest->created_by_user_id === $request->user()->id, 403);

        $borrowRequest->load([
            'items.item:id,code,name,tracking_method',
            'approval.approver:id,name',
            'loanTransaction',
        ]);

        $loan = $borrowRequest->loanTransaction;

        return Inertia::render('borrowing/Show', [
            'request' => [
                ...$borrowRequest->only([
                    'id', 'reference_no', 'category', 'pickup_at', 'return_at',
                    'usage_location', 'purpose', 'notes', 'status',
                    'submitted_at', 'cancelled_at', 'cancellation_reason',
                ]),
                'approval' => $borrowRequest->approval?->only(['decision', 'notes', 'decided_at']),
                'loan_status' => $loan?->status,
                'loan' => $loan ? [
                    'id' => $loan->id,
                    'status' => $loan->status,
                    'checked_out_at' => $loan->checked_out_at,
                    'due_at' => $loan->due_at,
                    'completed_at' => $loan->completed_at,
                    'return_status' => $loan->returnRequests()->latest('id')->value('status'),
                ] : null,
                'items' => $borrowRequest->items->map(fn ($line) => [
                    'item_id' => $line->item_id,
                    'code' => $line->item?->code,
                    'name' => $line->item?->name,
                    'quantity' => $line->quantity,
                    'notes' => $line->notes,
                ]),
                'extension' => $loan?->extensionRequests()
                    ->latest('id')
                    ->first()
                    ?->only(['id', 'status', 'current_due_at', 'requested_due_at', 'reason', 'decided_at', 'decision_notes']),
                'canRequestExtension' => $loan !== null
                    && $loan->status === 'borrowed'
                    && $loan->due_at !== null
                    && now()->lt($loan->due_at)
                    && $request->user()->member_id === $borrowRequest->member_id
                    && ! $loan->extensionRequests()->where('status', 'pending')->exists(),
            ],
        ]);
    }

    public function cancel(
        Request $request,
        BorrowRequest $borrowRequest,
        ReservationService $reservations,
        AuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();
        $isOwner = $borrowRequest->created_by_user_id === $user->id;
        $isApprover = $user->hasAnyRole(['admin_korlab', 'wm']);

        abort_unless($isOwner || $isApprover, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        DB::transaction(function () use ($borrowRequest, $data, $reservations, $audit): void {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pending_approval', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini tidak bisa dibatalkan.',
                ]);
            }

            $loan = $locked->loanTransaction;

            if ($loan && $loan->status !== 'reserved') {
                throw ValidationException::withMessages([
                    'status' => 'Barang sudah diserahkan; gunakan alur pengembalian.',
                ]);
            }

            $reservations->release($locked);

            $beforeStatus = $locked->status;

            $loan?->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);

            $audit->record(
                'borrow_request.cancelled',
                $locked,
                ['reason' => $data['reason']],
                ['status' => $beforeStatus],
                ['status' => 'cancelled'],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan dibatalkan.']);

        return back();
    }
}
