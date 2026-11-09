<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\DamageReportRequest;
use App\Http\Requests\Admin\Inventory\DamageResolveRequest;
use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Item;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DamageReportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-inventory');

        $status = $request->input('status');

        return Inertia::render('admin/inventory/damage/Index', [
            'reports' => DamageReport::query()
                ->with([
                    'item:id,code,name,tracking_method',
                    'asset:id,asset_code',
                    'reporter:id,name',
                    'resolver:id,name',
                ])
                ->when(in_array($status, ['open', 'resolved'], true), fn ($query) => $query->where('status', $status))
                ->latest('reported_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['status' => is_string($status) ? $status : ''],
        ]);
    }

    public function store(
        DamageReportRequest $request,
        Item $item,
        AuditLogger $audit,
        AvailabilityService $availability,
    ): RedirectResponse {
        Gate::authorize('manage-inventory');

        $data = $request->validated();
        $asset = null;
        $quantity = null;

        if ($item->tracking_method === 'serialized') {
            if (empty($data['asset_id'])) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Pilih unit yang bermasalah.',
                ]);
            }

            $asset = Asset::whereKey($data['asset_id'])->where('item_id', $item->id)->first();

            if (! $asset) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Unit tidak termasuk barang ini.',
                ]);
            }

            $quantity = 1;
        } else {
            $quantity = (int) ($data['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah wajib diisi.',
                ]);
            }

            if ($quantity > $availability->summary($item)['available']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah melebihi stok tersedia.',
                ]);
            }
        }

        $report = DB::transaction(function () use ($request, $item, $asset, $quantity, $data): DamageReport {
            $report = DamageReport::create([
                'item_id' => $item->id,
                'asset_id' => $asset?->id,
                'type' => $data['type'],
                'quantity' => $quantity,
                'description' => $data['description'],
                'status' => 'open',
                'reported_by_user_id' => $request->user()->id,
                'reported_at' => now(),
            ]);

            if ($asset) {
                $asset->update($this->assetStateFor($data['type']));
            }

            return $report;
        });

        $audit->record('damage.reported', $report, [
            'item_id' => $item->id,
            'asset_id' => $asset?->id,
            'type' => $data['type'],
            'quantity' => $quantity,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dibuat.']);

        return back();
    }

    public function resolve(
        DamageResolveRequest $request,
        DamageReport $damageReport,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('manage-inventory');

        if ($damageReport->status !== 'open') {
            throw ValidationException::withMessages([
                'resolution' => 'Laporan sudah diselesaikan.',
            ]);
        }

        $resolution = $request->validated('resolution');

        DB::transaction(function () use ($request, $damageReport, $resolution): void {
            if ($damageReport->asset_id) {
                $damageReport->asset->update(
                    $resolution === 'repaired'
                        ? ['status' => 'available', 'condition' => 'good']
                        : ['status' => 'lost'],
                );
            }

            if ($resolution === 'written_off' && $damageReport->item->tracking_method === 'quantity') {
                $item = $damageReport->item;
                $item->update([
                    'quantity_total' => max(0, (int) $item->quantity_total - (int) $damageReport->quantity),
                ]);
            }

            $damageReport->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'resolution_notes' => $request->validated('resolution_notes'),
                'resolved_by_user_id' => $request->user()->id,
                'resolved_at' => now(),
            ]);
        });

        $audit->record('damage.resolved', $damageReport, ['resolution' => $resolution]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan diselesaikan.']);

        return back();
    }

    /**
     * @return array{status: string, condition: string}
     */
    private function assetStateFor(string $type): array
    {
        return match ($type) {
            'loss' => ['status' => 'lost', 'condition' => 'damaged'],
            'maintenance' => ['status' => 'maintenance', 'condition' => 'good'],
            default => ['status' => 'damaged', 'condition' => 'damaged'],
        };
    }
}
