<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\LocationRequest;
use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-inventory');

        return Inertia::render('admin/inventory/locations/Index', [
            'locations' => Location::withCount('items')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(LocationRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $location = Location::create($request->validated());

        $audit->record('location.created', $location, ['code' => $location->code]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi dibuat.']);

        return back();
    }

    public function update(LocationRequest $request, Location $location, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $before = $location->only(['name', 'code', 'description']);

        $location->update($request->validated());

        $audit->record('location.updated', $location, [], $before, $location->only(['name', 'code', 'description']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi diperbarui.']);

        return back();
    }
}
