<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\CheckoutController;
use App\Http\Controllers\Admin\ExtensionController as AdminExtensionController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Borrowing\ExtensionController as BorrowingExtensionController;
use App\Http\Controllers\Borrowing\RequestController;
use App\Http\Controllers\Borrowing\ReturnController as BorrowingReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('peminjaman')->name('borrowing.')->group(function () {
    Route::get('/', [RequestController::class, 'index'])->name('index');
    Route::get('baru', [RequestController::class, 'create'])->name('create');
    Route::post('/', [RequestController::class, 'store'])->middleware('role:user')->name('store');
    Route::get('{borrowRequest}', [RequestController::class, 'show'])->name('show');
    Route::put('{borrowRequest}/cancel', [RequestController::class, 'cancel'])->name('cancel');
    Route::post('{loanTransaction}/return', [BorrowingReturnController::class, 'store'])
        ->middleware('role:user')
        ->name('return');
    Route::post('{loanTransaction}/extensions', [BorrowingExtensionController::class, 'store'])
        ->middleware('role:user')
        ->name('extensions.store');
});

Route::middleware(['auth', 'role:admin_korlab,wm'])->prefix('admin/approval')->name('admin.approval.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::get('{borrowRequest}', [ApprovalController::class, 'show'])->name('show');
    Route::post('{borrowRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::post('{borrowRequest}/reject', [ApprovalController::class, 'reject'])->name('reject');
});

Route::middleware(['auth', 'role:admin_korlab,wm'])->prefix('admin/extensions')->name('admin.extensions.')->group(function () {
    Route::get('/', [AdminExtensionController::class, 'index'])->name('index');
    Route::post('{extension}/approve', [AdminExtensionController::class, 'approve'])->name('approve');
    Route::post('{extension}/reject', [AdminExtensionController::class, 'reject'])->name('reject');
});

Route::middleware(['auth', 'role:admin_korlab'])->prefix('admin/checkout')->name('admin.checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::get('{loanTransaction}', [CheckoutController::class, 'show'])->name('show');
    Route::post('{loanTransaction}/scan', [CheckoutController::class, 'scan'])->name('scan');
    Route::post('{loanTransaction}', [CheckoutController::class, 'store'])->name('store');
});

Route::middleware(['auth', 'role:admin_korlab'])->prefix('admin/returns')->name('admin.returns.')->group(function () {
    Route::get('/', [AdminReturnController::class, 'index'])->name('index');
    Route::get('{returnRequest}', [AdminReturnController::class, 'show'])->name('show');
    Route::post('{returnRequest}/verify', [AdminReturnController::class, 'verify'])->name('verify');
    Route::post('{returnRequest}/cancel', [AdminReturnController::class, 'cancel'])->name('cancel');
});
