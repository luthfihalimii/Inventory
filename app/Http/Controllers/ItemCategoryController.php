<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        return view('item-categories.index', [
            'categories' => ItemCategory::withCount('inventoryItems')->orderBy('nama')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', 'unique:item_categories,nama'],
            'deskripsi' => ['nullable', 'string'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        $category = ItemCategory::create($data);
        $this->writeAudit('CREATE', 'ItemCategory', $category->id, null, $category->toArray());

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('item_categories', 'nama')->ignore($itemCategory->id)],
            'deskripsi' => ['nullable', 'string'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        $old = $itemCategory->toArray();
        $itemCategory->update($data);
        $this->writeAudit('UPDATE', 'ItemCategory', $itemCategory->id, $old, $itemCategory->fresh()->toArray());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function deactivate(ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->update(['status_aktif' => false]);
        $this->writeAudit('DEACTIVATE', 'ItemCategory', $itemCategory->id, null, ['status_aktif' => false]);

        return back()->with('success', 'Kategori dinonaktifkan.');
    }
}
