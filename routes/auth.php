<?php

use App\Http\Controllers\Auth\PasswordChangeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'update'])->name('password.change.store');
});
