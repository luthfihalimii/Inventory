<?php

use App\Http\Controllers\Admin\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin_korlab'])
    ->prefix('admin/announcements')
    ->name('admin.announcements.')
    ->group(function () {
        Route::get('/', [AnnouncementController::class, 'index'])->name('index');
        Route::post('/', [AnnouncementController::class, 'store'])->name('store');
        Route::put('{announcement}', [AnnouncementController::class, 'update'])->name('update');
        Route::put('{announcement}/status', [AnnouncementController::class, 'publish'])->name('status');
    });
