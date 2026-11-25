<?php

namespace App\Http\Controllers;

use App\Models\LockerLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LockerLocationController extends Controller
{
    public function index(): View
    {
        return view('locker-locations.index', [
            'locations' => LockerLocation::withCount('loanRequests')->orderBy('nama')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', 'unique:locker_locations,nama'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        $location = LockerLocation::create($data);
        $this->writeAudit('CREATE', 'LockerLocation', $location->id, null, $location->toArray());

        return back()->with('success', 'Lokasi loker berhasil ditambahkan.');
    }

    public function update(Request $request, LockerLocation $lockerLocation): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('locker_locations', 'nama')->ignore($lockerLocation->id)],
            'status_aktif' => ['required', 'boolean'],
        ]);

        if (! $data['status_aktif']) {
            $this->assertLocationCanBeDeactivated($lockerLocation);
        }

        $old = $lockerLocation->toArray();
        $lockerLocation->update($data);
        $this->writeAudit('UPDATE', 'LockerLocation', $lockerLocation->id, $old, $lockerLocation->fresh()->toArray());

        return back()->with('success', 'Lokasi loker berhasil diperbarui.');
    }

    public function deactivate(LockerLocation $lockerLocation): RedirectResponse
    {
        $this->assertLocationCanBeDeactivated($lockerLocation);
        $lockerLocation->update(['status_aktif' => false]);
        $this->writeAudit('DEACTIVATE', 'LockerLocation', $lockerLocation->id, null, ['status_aktif' => false]);

        return back()->with('success', 'Lokasi loker dinonaktifkan.');
    }

    private function assertLocationCanBeDeactivated(LockerLocation $lockerLocation): void
    {
        $hasOpenLoans = $lockerLocation->loanRequests()
            ->whereIn('status', ['PENDING', 'APPROVED', 'NEEDS_ADJUSTMENT', 'BORROWED', 'RETURN_PENDING', 'RETURN_ISSUE'])
            ->exists();

        if ($hasOpenLoans) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status_aktif' => 'Lokasi tidak dapat dinonaktifkan selama masih digunakan pengajuan yang belum selesai.',
            ]);
        }
    }
}
