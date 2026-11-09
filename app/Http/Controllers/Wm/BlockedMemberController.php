<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\MemberBorrowingBlock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockedMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'active');

        return Inertia::render('wm/blocked/Index', [
            'blocks' => MemberBorrowingBlock::query()
                ->with([
                    'member:id,name,nrp,borrowing_status',
                    'loanTransaction:id,borrow_request_id,due_at,overdue_at,status',
                    'loanTransaction.borrowRequest:id,reference_no',
                ])
                ->when($status === 'active', fn ($query) => $query->whereNull('ended_at'))
                ->when($status === 'released', fn ($query) => $query->whereNotNull('ended_at'))
                ->latest('started_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => in_array($status, ['active', 'released', 'all'], true) ? $status : 'active'],
        ]);
    }
}
