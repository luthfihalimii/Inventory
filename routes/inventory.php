<?php

use App\Http\Controllers\Admin\Inventory\AssetController;
use App\Http\Controllers\Admin\Inventory\CategoryController;
use App\Http\Controllers\Admin\Inventory\DamageReportController;
use App\Http\Controllers\Admin\Inventory\ItemController;
use App\Http\Controllers\Admin\Inventory\LocationController;
use App\Http\Controllers\Inventory\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('inventory', [CatalogController::class, 'index'])->name('inventory.index');
    Route::get('inventory/{item}', [CatalogController::class, 'show'])->name('inventory.show');
});

Route::middleware(['auth', 'role:admin_korlab'])->prefix('admin/inventory')->name('admin.inventory.')->group(function () {
    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/qr', [ItemController::class, 'qrBatch'])->name('items.qr.batch');
    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::post('items/import', [ItemController::class, 'import'])->name('items.import');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::get('items/{item}/qr', [ItemController::class, 'qr'])->name('items.qr');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
    Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
    Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');

    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');

    Route::get('damage-reports', [DamageReportController::class, 'index'])->name('damage-reports.index');
    Route::post('items/{item}/damage-reports', [DamageReportController::class, 'store'])->name('damage-reports.store');
    Route::put('damage-reports/{damageReport}/resolve', [DamageReportController::class, 'resolve'])->name('damage-reports.resolve');
});
