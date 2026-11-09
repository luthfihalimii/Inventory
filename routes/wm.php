<?php

use App\Http\Controllers\Wm\AccountController;
use App\Http\Controllers\Wm\AuditLogController;
use App\Http\Controllers\Wm\DivisionController;
use App\Http\Controllers\Wm\MemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:wm'])->prefix('wm')->name('wm.')->group(function () {
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::post('members', [MemberController::class, 'store'])->name('members.store');
    Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');

    Route::get('divisions', [DivisionController::class, 'index'])->name('divisions.index');
    Route::post('divisions', [DivisionController::class, 'store'])->name('divisions.store');
    Route::put('divisions/{division}', [DivisionController::class, 'update'])->name('divisions.update');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('accounts/{account}/roles', [AccountController::class, 'updateRole'])->name('accounts.roles.update');
    Route::put('accounts/{account}/password', [AccountController::class, 'updatePassword'])->name('accounts.password.update');
    Route::put('accounts/{account}/status', [AccountController::class, 'updateStatus'])->name('accounts.status.update');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
});
