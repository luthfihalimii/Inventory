<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wm\DivisionRequest;
use App\Models\Division;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', Division::class);

        return Inertia::render('wm/divisions/Index', [
            'divisions' => Division::withCount('members')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(DivisionRequest $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', Division::class);

        $division = Division::create($request->validated());

        $audit->record('division.created', $division, ['code' => $division->code]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Divisi dibuat.']);

        return back();
    }

    public function update(DivisionRequest $request, Division $division, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('manage', Division::class);

        $before = $division->only(['name', 'code']);

        $division->update($request->validated());

        $audit->record('division.updated', $division, [], $before, $division->only(['name', 'code']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Divisi diperbarui.']);

        return back();
    }
}
