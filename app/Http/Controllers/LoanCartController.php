<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanCartController extends Controller
{
    private const SOP_VERSION = 'ENT-LOAN-SOP-DRAFT-1';

    public function index(Request $request): View
    {
        $cart = collect($request->session()->get('loan_cart', []));
        $items = InventoryItem::with(['category', 'units'])
            ->whereIn('id', $cart->keys())->get()->keyBy('id');

        return view('loan-requests.cart', compact('cart', 'items'));
    }

    public function add(Request $request, InventoryItem $inventoryItem)
    {
        abort_unless($inventoryItem->status === 'ACTIVE', 404);
        $data = $request->validate(['jumlah' => ['required', 'integer', 'min:1']]);
        $available = $inventoryItem->units()
            ->where('status', 'AVAILABLE')->where('kondisi', '!=', 'DAMAGED')->count();

        $cart = $request->session()->get('loan_cart', []);
        $newQuantity = (int) ($cart[$inventoryItem->id] ?? 0) + (int) $data['jumlah'];
        if ($available < 1 || $newQuantity > $available) {
            $message = 'Jumlah melebihi unit yang tersedia. Maksimal yang dapat ditambahkan: ' . max(0, $available - (int) ($cart[$inventoryItem->id] ?? 0)) . ' unit.';
            if ($request->expectsJson()) return response()->json(['message' => $message], 422);
            throw ValidationException::withMessages(['jumlah' => $message]);
        }

        $cart[$inventoryItem->id] = $newQuantity;
        $request->session()->put('loan_cart', $cart);
        $request->session()->forget('loan_sop_acceptance');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Barang berhasil ditambahkan ke keranjang.',
                'cart_count' => array_sum($cart),
                'item_count' => count($cart),
            ]);
        }

        return back()->with('success', 'Barang berhasil ditambahkan ke keranjang.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $cart = [];
        foreach ($data['items'] as $id => $quantity) {
            $id = (int) $id;
            $quantity = (int) ($quantity ?? 0);
            if ($quantity === 0) continue;
            $item = InventoryItem::withCount(['units as available_units' => fn ($q) => $q->where('status', 'AVAILABLE')->where('kondisi', '!=', 'DAMAGED')])
                ->where('status', 'ACTIVE')->find($id);
            if (! $item || $quantity > (int) $item->available_units) {
                throw ValidationException::withMessages([
                    'items' => 'Kuantitas salah satu barang melebihi unit yang tersedia. Periksa kembali keranjang.',
                ]);
            }
            $cart[$id] = $quantity;
        }

        $request->session()->put('loan_cart', $cart);
        $request->session()->forget('loan_sop_acceptance');
        return back()->with('success', 'Keranjang berhasil diperbarui. Persetujuan SOP perlu dilakukan kembali sebelum checkout.');
    }

    public function acceptSop(Request $request): RedirectResponse
    {
        if (empty($request->session()->get('loan_cart', []))) return redirect()->route('loan-cart.index')->with('error', 'Keranjang masih kosong.');
        $request->validate(['sop_accepted' => ['accepted']]);
        $request->session()->put('loan_sop_acceptance', [
            'version' => self::SOP_VERSION,
            'accepted_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('loan-requests.create');
    }

    public function remove(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $cart = $request->session()->get('loan_cart', []);
        unset($cart[$inventoryItem->id]);
        $request->session()->put('loan_cart', $cart);
        $request->session()->forget('loan_sop_acceptance');
        return back()->with('success', 'Barang dihapus dari keranjang. Persetujuan SOP perlu dilakukan kembali sebelum checkout.');
    }
}
