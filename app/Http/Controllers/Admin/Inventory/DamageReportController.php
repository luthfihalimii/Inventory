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

        DB::transaction(function () use ($request, $item, $data, $audit, $availability): void {
            $item = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = ['asset_status' => null, 'quantity_total' => $item->quantity_total];

            $asset = null;
            $quantity = null;

            if ($item->tracking_method === 'serialized') {
                if (empty($data['asset_id'])) {
                    throw ValidationException::withMessages(['asset_id' => 'Pilih unit yang bermasalah.']);
                }

                $asset = Asset::whereKey($data['asset_id'])
                    ->where('item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if (! $asset) {
                    throw ValidationException::withMessages(['asset_id' => 'Unit tidak termasuk barang ini.']);
                }

                if (in_array($asset->status, ['lost', 'inactive'], true)) {
                    throw ValidationException::withMessages(['asset_id' => 'Unit sudah hilang atau nonaktif.']);
                }

                if ($asset->damageReports()->where('status', 'open')->exists()) {
                    throw ValidationException::withMessages(['asset_id' => 'Unit sudah punya laporan terbuka.']);
                }

                $before['asset_status'] = $asset->status;
                $quantity = 1;
            } else {
                $quantity = (int) ($data['quantity'] ?? 0);

                if ($quantity < 1) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah wajib diisi.']);
                }

                if ($quantity > $availability->summary($item)['available']) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah melebihi stok tersedia.']);
                }
            }

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

            $audit->record(
                'damage.reported',
                $report,
                ['item_id' => $item->id, 'asset_id' => $asset?->id, 'type' => $data['type'], 'quantity' => $quantity],
                $before,
                [
                    'asset_status' => $asset?->fresh()?->status,
                    'quantity_total' => $item->fresh()->quantity_total,
                ],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dibuat.']);

        return back();
    }

    public function resolve(
        DamageResolveRequest $request,
        DamageReport $damageReport,
        AuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('manage-inventory');

        $resolution = $request->validated('resolution');
        $cost = $request->validated('cost');
        $vendor = $request->validated('vendor');

        DB::transaction(function () use ($request, $damageReport, $resolution, $cost, $vendor, $audit): void {
            $report = DamageReport::whereKey($damageReport->id)->lockForUpdate()->firstOrFail();

            if ($report->status !== 'open') {
                throw ValidationException::withMessages(['resolution' => 'Laporan sudah diselesaikan.']);
            }

            $item = Item::whereKey($report->item_id)->lockForUpdate()->firstOrFail();

            $before = [
                'asset_status' => $report->asset_id ? Asset::whereKey($report->asset_id)->value('status') : null,
                'quantity_total' => $item->quantity_total,
            ];

            if ($report->asset_id) {
                Asset::whereKey($report->asset_id)->update(
                    $resolution === 'repaired'
                        ? ['status' => 'available', 'condition' => 'good']
                        : ['status' => 'lost'],
                );
            }

            if ($resolution === 'written_off' && $item->tracking_method === 'quantity') {
                if ((int) $report->quantity > (int) $item->quantity_total) {
                    throw ValidationException::withMessages([
                        'resolution' => 'Jumlah laporan melebihi stok total.',
                    ]);
                }

                $item->update(['quantity_total' => (int) $item->quantity_total - (int) $report->quantity]);
            }

            $report->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'resolution_notes' => $request->validated('resolution_notes'),
                'cost' => $cost,
                'vendor' => $vendor,
                'resolved_by_user_id' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            $audit->record(
                'damage.resolved',
                $report,
                ['resolution' => $resolution],
                $before,
                [
                    'asset_status' => $report->asset_id ? Asset::whereKey($report->asset_id)->value('status') : null,
                    'quantity_total' => $item->fresh()->quantity_total,
                ],
            );
        });

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
