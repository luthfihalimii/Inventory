<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\ImportItemsRequest;
use App\Http\Requests\Admin\Inventory\ItemRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Services\AuditLogger;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): Response
    {
        Gate::authorize('manage-inventory');

        $search = $request->input('q');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->input('status');
        $categoryId = $request->input('category_id');

        $items = Item::query()
            ->with(['category', 'location'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $items->through(fn (Item $item) => [
            ...$item->only([
                'id', 'code', 'name', 'tracking_method', 'quantity_total', 'price',
                'status', 'notes',
            ]),
            'acquired_at' => $item->getRawOriginal('acquired_at'),
            'category' => $item->category?->only(['id', 'name']),
            'location' => $item->location?->only(['id', 'name', 'code']),
            'availability' => $availability->summary($item),
        ]);

        return Inertia::render('admin/inventory/items/Index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'code']),
            'filters' => [
                'q' => $search,
                'status' => is_string($status) ? $status : '',
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function store(ItemRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $item = new Item($request->validated());
        $item->forceFill(['qr_token' => Str::random(40)])->save();

        $audit->record('item.created', $item, ['code' => $item->code]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $item->tracking_method === 'serialized'
                ? 'Barang dibuat. Tambahkan unit agar bisa dipinjam.'
                : 'Barang dibuat.',
        ]);

        // Serialized items are unusable until they have units; send the admin straight there.
        if ($item->tracking_method === 'serialized') {
            return to_route('admin.inventory.assets.index', ['item_id' => $item->id]);
        }

        return back();
    }

    public function update(
        ItemRequest $request,
        Item $item,
        AuditLogger $audit,
        AvailabilityService $availability,
    ): RedirectResponse {
        Gate::authorize('manage-inventory');

        $data = $request->validated();

        if (($item->assets()->exists() || $item->damageReports()->exists()) && $data['tracking_method'] !== $item->tracking_method) {
            throw ValidationException::withMessages([
                'tracking_method' => 'Metode pelacakan tidak bisa diubah karena barang sudah punya unit atau laporan.',
            ]);
        }

        if ($item->tracking_method === 'quantity') {
            $blocked = $availability->blockedQuantity($item);

            if ((int) $data['quantity_total'] < $blocked) {
                throw ValidationException::withMessages([
                    'quantity_total' => "Jumlah tidak boleh di bawah laporan terbuka ({$blocked}).",
                ]);
            }
        }

        $before = $item->only([
            'code', 'name', 'category_id', 'location_id', 'tracking_method',
            'quantity_total', 'price', 'acquired_at', 'status', 'notes',
        ]);

        $item->update($data);

        $audit->record('item.updated', $item, [], $before, $item->only(array_keys($before)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Barang diperbarui.']);

        return back();
    }

    public function import(ImportItemsRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $rows = $this->parseImport($request->file('file'));
        $created = 0;

        DB::transaction(function () use ($rows, $audit, &$created): void {
            foreach ($rows as $row) {
                $item = new Item([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'category_id' => $row['category_id'],
                    'location_id' => $row['location_id'],
                    'tracking_method' => $row['tracking_method'],
                    'quantity_total' => $row['quantity_total'],
                    'price' => $row['price'],
                    'acquired_at' => $row['acquired_at'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                ]);
                $item->forceFill(['qr_token' => Str::random(40)])->save();

                foreach ($row['asset_codes'] as $assetCode) {
                    Asset::create([
                        'item_id' => $item->id,
                        'asset_code' => $assetCode,
                        'qr_token' => Str::random(40),
                        'condition' => 'good',
                        'status' => 'available',
                    ]);
                }

                $audit->record('item.created', $item, [
                    'code' => $item->code,
                    'source' => 'csv',
                    'assets' => count($row['asset_codes']),
                ]);

                $created++;
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$created} barang diimpor."]);

        return back();
    }

    public function qr(Item $item): Response
    {
        Gate::authorize('manage-inventory');

        return $this->renderQr(collect([$item]));
    }

    public function qrBatch(Request $request): Response
    {
        Gate::authorize('manage-inventory');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $items = Item::query()
            ->whereIn('id', $data['ids'])
            ->orderBy('name')
            ->get();

        return $this->renderQr($items);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    private function renderQr($items): Response
    {
        return Inertia::render('admin/inventory/items/Qr', [
            'items' => $items->map(fn (Item $item) => [
                ...$item->only(['id', 'code', 'name', 'qr_token', 'tracking_method']),
                'assets' => $item->assets()
                    ->whereNotIn('status', ['lost', 'inactive'])
                    ->orderBy('asset_code')
                    ->get(['id', 'asset_code', 'qr_token']),
            ])->values(),
        ]);
    }

    /**
     * Parse and validate the whole file before touching the database.
     *
     * @return array<int, array{code: string, name: string, category_id: int, location_id: int, tracking_method: string, quantity_total: int|null, price: string|null, acquired_at: string|null, status: string, notes: string|null, asset_codes: array<int, string>}>
     */
    private function parseImport(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca.']);
        }

        $errors = [];
        $rows = [];

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = is_array($header)
                ? array_map(fn ($cell) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell))), $header)
                : [];

            $required = ['code', 'name', 'category', 'location', 'tracking_method'];

            if (array_diff($required, $header) !== []) {
                throw ValidationException::withMessages([
                    'file' => 'Header wajib: '.implode(',', $required).'.',
                ]);
            }

            $index = array_flip($header);
            $categories = Category::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Category $category) => [strtolower(trim($category->name)) => $category->id])
                ->all();
            $locations = [];

            foreach (Location::query()->get(['id', 'name', 'code']) as $location) {
                $locations[strtolower(trim($location->name))] = $location->id;
                $locations[strtolower(trim($location->code))] = $location->id;
            }

            $lineNumber = 1;
            $cell = fn (array $line, string $column): string => isset($index[$column])
                ? trim((string) ($line[$index[$column]] ?? ''))
                : '';

            while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $lineNumber++;

                if ($line === [null] || trim(implode('', $line)) === '') {
                    continue;
                }

                $code = $cell($line, 'code');
                $name = $cell($line, 'name');
                $category = strtolower($cell($line, 'category'));
                $location = strtolower($cell($line, 'location'));
                $tracking = strtolower($cell($line, 'tracking_method'));
                $status = strtolower($cell($line, 'status')) ?: 'active';
                $price = $cell($line, 'price');
                $quantity = $cell($line, 'quantity_total');
                $acquiredAt = $cell($line, 'acquired_at');
                $notes = $cell($line, 'notes');
                $assetCodes = $this->splitAssetCodes($cell($line, 'asset_codes'));

                if ($code === '' || $name === '') {
                    $errors[] = "Baris {$lineNumber}: code dan name wajib diisi.";

                    continue;
                }

                if (! isset($categories[$category])) {
                    $errors[] = "Baris {$lineNumber}: kategori \"{$category}\" tidak dikenal.";

                    continue;
                }

                if (! isset($locations[$location])) {
                    $errors[] = "Baris {$lineNumber}: lokasi \"{$location}\" tidak dikenal.";

                    continue;
                }

                if (! in_array($tracking, ['serialized', 'quantity'], true)) {
                    $errors[] = "Baris {$lineNumber}: tracking_method harus serialized atau quantity.";

                    continue;
                }

                if (! in_array($status, ['active', 'inactive'], true)) {
                    $errors[] = "Baris {$lineNumber}: status harus active atau inactive.";

                    continue;
                }

                if ($acquiredAt !== '' && ! $this->isDate($acquiredAt)) {
                    $errors[] = "Baris {$lineNumber}: acquired_at harus format YYYY-MM-DD.";

                    continue;
                }

                if ($price !== '' && ! is_numeric($price)) {
                    $errors[] = "Baris {$lineNumber}: price harus angka.";

                    continue;
                }

                if ($tracking === 'quantity') {
                    if (! ctype_digit($quantity) || (int) $quantity < 0) {
                        $errors[] = "Baris {$lineNumber}: quantity_total wajib angka >= 0 untuk barang jumlah.";

                        continue;
                    }

                    $quantityTotal = (int) $quantity;
                    $assetCodes = [];
                } else {
                    if ($quantity !== '' && (int) $quantity > 0) {
                        $errors[] = "Baris {$lineNumber}: quantity_total hanya untuk barang jumlah.";

                        continue;
                    }

                    $quantityTotal = null;
                }

                $rows[] = [
                    'line' => $lineNumber,
                    'code' => $code,
                    'name' => $name,
                    'category_id' => $categories[$category],
                    'location_id' => $locations[$location],
                    'tracking_method' => $tracking,
                    'quantity_total' => $quantityTotal,
                    'price' => $price === '' ? null : $price,
                    'acquired_at' => $acquiredAt === '' ? null : $acquiredAt,
                    'status' => $status,
                    'notes' => $notes === '' ? null : $notes,
                    'asset_codes' => $assetCodes,
                ];

                if (count($rows) > 1000) {
                    throw ValidationException::withMessages(['file' => 'Maksimal 1000 baris per impor.']);
                }
            }
        } finally {
            fclose($handle);
        }

        if ($rows === [] && $errors === []) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi baris data.']);
        }

        $codes = array_count_values(array_column($rows, 'code'));
        $existingCodes = Item::query()->whereIn('code', array_keys($codes))->pluck('code')->all();

        $assetCodes = [];

        foreach ($rows as $row) {
            foreach ($row['asset_codes'] as $assetCode) {
                $assetCodes[$assetCode] = ($assetCodes[$assetCode] ?? 0) + 1;
            }
        }

        $existingAssets = Asset::query()->whereIn('asset_code', array_keys($assetCodes))->pluck('asset_code')->all();

        foreach ($rows as $row) {
            if ($codes[$row['code']] > 1) {
                $errors[] = "Baris {$row['line']}: kode {$row['code']} muncul lebih dari sekali di file.";
            }

            if (in_array($row['code'], $existingCodes, true)) {
                $errors[] = "Baris {$row['line']}: kode {$row['code']} sudah ada.";
            }

            foreach ($row['asset_codes'] as $assetCode) {
                if ($assetCodes[$assetCode] > 1) {
                    $errors[] = "Baris {$row['line']}: kode unit {$assetCode} muncul lebih dari sekali di file.";
                }

                if (in_array($assetCode, $existingAssets, true)) {
                    $errors[] = "Baris {$row['line']}: kode unit {$assetCode} sudah ada.";
                }
            }
        }

        if ($errors !== []) {
            $shown = array_slice(array_values(array_unique($errors)), 0, 15);

            throw ValidationException::withMessages([
                'file' => implode(' ', $shown).(count($errors) > 15 ? ' …' : ''),
            ]);
        }

        return array_map(function (array $row): array {
            unset($row['line']);

            return $row;
        }, $rows);
    }

    /**
     * @return array<int, string>
     */
    private function splitAssetCodes(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[;|]/', $value) ?: []),
            fn (string $code) => $code !== '',
        )));
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
