<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wm\MemberRequest;
use App\Models\Division;
use App\Models\Member;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage', Member::class);

        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';

        $members = Member::query()
            ->with('division')
            ->when($search !== '', fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('nrp', 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('wm/members/Index', [
            'members' => $members,
            'divisions' => Division::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => ['q' => $search],
        ]);
    }

    public function store(MemberRequest $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', Member::class);

        $member = Member::create($request->validated());

        $audit->record('member.created', $member, ['nrp' => $member->nrp]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota dibuat.']);

        return back();
    }

    public function update(MemberRequest $request, Member $member, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', Member::class);

        $fields = ['member_code', 'name', 'nrp', 'division_id', 'generation', 'position', 'notes', 'membership_status', 'borrowing_status'];

        $before = $member->only($fields);

        $member->update($request->validated());

        $audit->record(
            'member.updated',
            $member,
            ['nrp' => $member->nrp],
            $before,
            $member->only($fields),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anggota diperbarui.']);

        return back();
    }
}
