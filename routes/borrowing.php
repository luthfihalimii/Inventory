<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Borrowing\RequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('peminjaman')->name('borrowing.')->group(function () {
    Route::get('/', [RequestController::class, 'index'])->name('index');
    Route::get('baru', [RequestController::class, 'create'])->name('create');
    Route::post('/', [RequestController::class, 'store'])->middleware('role:user')->name('store');
    Route::get('{borrowRequest}', [RequestController::class, 'show'])->name('show');
    Route::put('{borrowRequest}/cancel', [RequestController::class, 'cancel'])->name('cancel');
});

Route::middleware(['auth', 'role:admin_korlab,wm'])->prefix('admin/approval')->name('admin.approval.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::get('{borrowRequest}', [ApprovalController::class, 'show'])->name('show');
    Route::post('{borrowRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('{borrowRequest}/reject', [ApprovalController::class, 'reject'])->name('reject');
});
