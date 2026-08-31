<?php

use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CreditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\PaymentByController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/dashboard');

Route::prefix('admin')->name('admin.')->group(function () {
    // Guests only - an authenticated admin hitting /admin/login goes to the dashboard.
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    });

    // Everything below requires an authenticated admin session.
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('expenses', ExpenseController::class)
            ->parameters(['expenses' => 'transaction'])
            ->except('show');
        Route::get('expenses/{transaction}', [ExpenseController::class, 'show'])->name('expenses.show');

        Route::resource('credits', CreditController::class)
            ->parameters(['credits' => 'transaction'])
            ->except('show');
        Route::get('credits/{transaction}', [CreditController::class, 'show'])->name('credits.show');

        // Receipts live on the private disk - these routes are the only way in.
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/export/{format}', ExportController::class)->name('transactions.export');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('payment-bys', [PaymentByController::class, 'index'])->name('payment-bys.index');
        Route::post('payment-bys', [PaymentByController::class, 'store'])->name('payment-bys.store');
        Route::put('payment-bys/{paymentBy}', [PaymentByController::class, 'update'])->name('payment-bys.update');
        Route::patch('payment-bys/{paymentBy}/toggle', [PaymentByController::class, 'toggle'])->name('payment-bys.toggle');
        Route::delete('payment-bys/{paymentBy}', [PaymentByController::class, 'destroy'])->name('payment-bys.destroy');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences');
    });
});
