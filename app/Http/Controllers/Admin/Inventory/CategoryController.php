<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-inventory');

        return Inertia::render('admin/inventory/categories/Index', [
            'categories' => Category::withCount('items')->orderBy('name')->paginate(50),
        ]);
    }

    public function store(CategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $category = Category::create($request->validated());

        $audit->record('category.created', $category, ['name' => $category->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori dibuat.']);

        return back();
    }

    public function update(CategoryRequest $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');

        $before = $category->only(['name', 'description']);

        $category->update($request->validated());

        $audit->record('category.updated', $category, [], $before, $category->only(['name', 'description']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori diperbarui.']);

        return back();
    }
}
