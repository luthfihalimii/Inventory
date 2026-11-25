<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\LoanCartController;
use App\Http\Controllers\ItemIssueReportController;
use App\Http\Controllers\InventoryUnitController;
use App\Http\Controllers\InventoryUnitImportController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\LoanRequestController;
use App\Http\Controllers\MaintenanceRecordController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberExportController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\MonitoringExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReturnRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Routes untuk seluruh pengguna yang sudah login
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/loan-cart', [LoanCartController::class, 'index'])->middleware('role:USER')->name('loan-cart.index');
    Route::post('/loan-cart/checkout', [LoanCartController::class, 'acceptSop'])->middleware('role:USER')->name('loan-cart.accept-sop');
    Route::post('/loan-cart/{inventoryItem}', [LoanCartController::class, 'add'])->middleware('role:USER')->name('loan-cart.add');
    Route::put('/loan-cart', [LoanCartController::class, 'update'])->middleware('role:USER')->name('loan-cart.update');
    Route::delete('/loan-cart/{inventoryItem}', [LoanCartController::class, 'remove'])->middleware('role:USER')->name('loan-cart.remove');
    Route::get('/item-issues', [ItemIssueReportController::class, 'index'])->name('item-issues.index');
    Route::get('/item-issues/create', [ItemIssueReportController::class, 'create'])->middleware('role:USER')->name('item-issues.create');
    Route::post('/item-issues', [ItemIssueReportController::class, 'store'])->middleware('role:USER')->name('item-issues.store');
    Route::patch('/item-issues/{itemIssue}', [ItemIssueReportController::class, 'update'])->middleware('role:ADMIN')->name('item-issues.update');
    Route::get('/account/settings', [AccountSettingsController::class, 'edit'])->name('account.settings');
    Route::put('/account/settings', [AccountSettingsController::class, 'update'])->name('account.settings.update');
    Route::post('/session/keepalive', fn (\Illuminate\Http\Request $request) => response()->json(['ok' => true]))->name('session.keepalive');
    Route::view('/sop', 'pages.sop')->name('sop');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/inventory-items/create', [InventoryItemController::class, 'create'])
            ->name('inventory-items.create');
        Route::get('/inventory-items/import', [\App\Http\Controllers\InventoryItemImportController::class, 'index'])->name('inventory-items.import');
        Route::get('/inventory-items/import/template', [\App\Http\Controllers\InventoryItemImportController::class, 'template'])->name('inventory-items.import.template');
        Route::post('/inventory-items/import', [\App\Http\Controllers\InventoryItemImportController::class, 'store'])->name('inventory-items.import.store');
    });

    Route::middleware('role:USER,ADMIN,SUPER_ADMIN')->group(function () {
        // Inventaris dapat dilihat semua role; pengelolaan dibatasi di bawah.
        Route::get('/inventory-items', [InventoryItemController::class, 'index'])
            ->name('inventory-items.index');
        Route::get('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'show'])
            ->name('inventory-items.show');

        // Semua role boleh melihat transaksi sesuai pembatasan controller.
        Route::get('/loan-requests', [LoanRequestController::class, 'index'])
            ->name('loan-requests.index');
        Route::get('/loan-requests/{loanRequest}', [LoanRequestController::class, 'show'])
            ->whereNumber('loanRequest')
            ->name('loan-requests.show');

        Route::get('/return-records', [ReturnRecordController::class, 'index'])
            ->name('return-records.index');
        Route::get('/return-records/{returnRecord}', [ReturnRecordController::class, 'show'])->name('return-records.show');
        Route::get('/return-photos/{returnPhoto}', [ReturnRecordController::class, 'photo'])->name('return-photos.show');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/images/{filename}', [AnnouncementController::class, 'showImage'])->name('announcements.images.show');

        Route::post('/loan-requests/{loanRequest}/comments', [CommentController::class, 'store'])
            ->name('loan-requests.comments.store');
    });

    /*
    | USER: pengajuan dan pengembalian miliknya sendiri.
    */
    Route::middleware('role:USER')->group(function () {
        Route::get('/loan-requests/create', [LoanRequestController::class, 'create'])
            ->name('loan-requests.create');
        Route::post('/loan-requests', [LoanRequestController::class, 'store'])
            ->name('loan-requests.store');
        Route::post('/loan-requests/{loanRequest}/cancel', [LoanRequestController::class, 'cancel'])
            ->name('loan-requests.cancel');

        Route::post('/loan-requests/{loanRequest}/return', [ReturnRecordController::class, 'store'])
            ->name('return-records.store');
    });

    /*
    | ADMIN/Korlab: pengelolaan inventaris dan keputusan transaksi.
    */
    Route::middleware('role:ADMIN')->group(function () {
        // Master inventaris
        Route::post('/inventory-items', [InventoryItemController::class, 'store'])->name('inventory-items.store');
        Route::post('/inventory-items/bulk-deactivate', [InventoryItemController::class, 'bulkDeactivate'])->name('inventory-items.bulk-deactivate');
        Route::get('/inventory-items/{inventoryItem}/edit', [InventoryItemController::class, 'edit'])
            ->name('inventory-items.edit');
        Route::put('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'update'])
            ->name('inventory-items.update');
        Route::post('/inventory-items/{inventoryItem}/deactivate', [InventoryItemController::class, 'deactivate'])
            ->name('inventory-items.deactivate');

        Route::get('/inventory-units', [InventoryUnitController::class, 'index'])
            ->name('inventory-units.index');
        Route::get('/inventory-units/import', [InventoryUnitImportController::class, 'index'])->name('inventory-units.import');
        Route::get('/inventory-units/import/template', [InventoryUnitImportController::class, 'template'])->name('inventory-units.import.template');
        Route::post('/inventory-units/import', [InventoryUnitImportController::class, 'import'])->name('inventory-units.import.store');
        Route::get('/inventory-units/export', [InventoryUnitImportController::class, 'export'])->name('inventory-units.export');
        Route::get('/inventory-units/create', [InventoryUnitController::class, 'create'])
            ->name('inventory-units.create');
        Route::post('/inventory-units', [InventoryUnitController::class, 'store'])
            ->name('inventory-units.store');
        Route::get('/inventory-units/{inventoryUnit}/edit', [InventoryUnitController::class, 'edit'])
            ->name('inventory-units.edit');
        Route::put('/inventory-units/{inventoryUnit}', [InventoryUnitController::class, 'update'])
            ->name('inventory-units.update');

        Route::get('/item-categories', [ItemCategoryController::class, 'index'])
            ->name('item-categories.index');
        Route::post('/item-categories', [ItemCategoryController::class, 'store'])
            ->name('item-categories.store');
        Route::put('/item-categories/{itemCategory}', [ItemCategoryController::class, 'update'])
            ->name('item-categories.update');
        Route::post('/item-categories/{itemCategory}/deactivate', [ItemCategoryController::class, 'deactivate'])
            ->name('item-categories.deactivate');


        Route::get('/maintenance-records', [MaintenanceRecordController::class, 'index'])
            ->name('maintenance-records.index');
        Route::get('/maintenance-records/create', [MaintenanceRecordController::class, 'create'])
            ->name('maintenance-records.create');
        Route::post('/maintenance-records', [MaintenanceRecordController::class, 'store'])
            ->name('maintenance-records.store');
        Route::put('/maintenance-records/{maintenanceRecord}', [MaintenanceRecordController::class, 'update'])
            ->name('maintenance-records.update');

        // Keputusan peminjaman
        Route::post('/loan-requests/{loanRequest}/approve', [LoanRequestController::class, 'approve'])
            ->name('loan-requests.approve');
        Route::post('/loan-requests/{loanRequest}/reject', [LoanRequestController::class, 'reject'])
            ->name('loan-requests.reject');
        Route::post('/loan-requests/{loanRequest}/mark-borrowed', [LoanRequestController::class, 'markBorrowed'])
            ->name('loan-requests.mark-borrowed');
        Route::post('/loan-requests/{loanRequest}/resolve-adjustment', [LoanRequestController::class, 'resolveAdjustment'])
            ->name('loan-requests.resolve-adjustment');
        Route::post('/loan-requests/{loanRequest}/cancel-admin', [LoanRequestController::class, 'cancelApproved'])
            ->name('loan-requests.cancel-admin');
        Route::post('/loan-requests/{loanRequest}/cancellation-review', [LoanRequestController::class, 'reviewCancellation'])
            ->name('loan-requests.cancellation-review');

        // Verifikasi pengembalian
        Route::post('/return-records/{returnRecord}/verify', [ReturnRecordController::class, 'verify'])
            ->name('return-records.verify');
    });

    /*
    | ADMIN dan SUPER_ADMIN: pengumuman dan audit.
    */
    Route::middleware('role:ADMIN,SUPER_ADMIN')->group(function () {
        Route::get('/reports/export/inventory', [MonitoringExportController::class, 'inventory'])->name('reports.export.inventory');
        Route::get('/reports/export/loans', [MonitoringExportController::class, 'loans'])->name('reports.export.loans');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])
            ->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::post('/announcements/images', [AnnouncementController::class, 'uploadImage'])->name('announcements.images.upload');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])
            ->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])
            ->name('announcements.update');
        Route::post('/announcements/{announcement}/deactivate', [AnnouncementController::class, 'deactivate'])
            ->name('announcements.deactivate');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    });

    /*
    | SUPER_ADMIN/WM: manajemen anggota. Tidak diberi route keputusan transaksi.
    */
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/export', [MemberExportController::class, 'export'])->name('members.export');
        Route::get('/members/import', [MemberImportController::class, 'index'])->name('members.import');
        Route::get('/members/import/template', [MemberImportController::class, 'template'])->name('members.import.template');
        Route::post('/members/import', [MemberImportController::class, 'import'])->name('members.import.store');
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('/members/{member}/deactivate', [MemberController::class, 'deactivate'])
            ->name('members.deactivate');
    });
});
