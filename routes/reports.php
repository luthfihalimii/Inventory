<?php

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin_korlab,wm'])->prefix('admin/reports')->name('admin.reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('{type}.csv', [ReportController::class, 'export'])
        ->whereIn('type', ['loans', 'returns', 'damage', 'audit'])
        ->name('export');
});
