<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CreditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\PaymentByController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\UserActivityController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TransactionController;
use Illuminate\Support\Facades\Route;

// Both the site root and the bare panel root land on the dashboard. Every
// route below carries a segment after "admin/", so without this second line
// /admin itself is a 404 rather than the entry point people expect to type.
// Where that lands is then the auth middleware's business: a signed-in admin
// gets the dashboard, anyone else is sent to the login screen.
Route::redirect('/', '/admin/dashboard');
Route::redirect('/admin', '/admin/dashboard');

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

        // The expandable Company -> Project -> Person tree.
        Route::get('hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');

        // Company -> Project -> Person. Declared before the money modules so
        // the hierarchy a transaction points at always exists first.
        Route::resource('companies', CompanyController::class);
        Route::patch('companies/{company}/toggle', [CompanyController::class, 'toggle'])->name('companies.toggle');

        Route::resource('projects', ProjectController::class);
        Route::patch('projects/{project}/toggle', [ProjectController::class, 'toggle'])->name('projects.toggle');
        Route::post('projects/{project}/people', [ProjectController::class, 'attachPeople'])->name('projects.people.attach');
        Route::delete('projects/{project}/people/{person}', [ProjectController::class, 'detachPerson'])
            ->name('projects.people.detach');

        Route::resource('people', PersonController::class)->parameters(['people' => 'person']);
        Route::patch('people/{person}/toggle', [PersonController::class, 'toggle'])->name('people.toggle');

        // Equal partner distribution. The plan is always recalculated from the
        // transactions; these routes only record the payments that settle it.
        Route::get('projects/{project}/settlement', [SettlementController::class, 'project'])
            ->name('projects.settlement');
        Route::post('projects/{project}/settlement', [SettlementController::class, 'store'])
            ->name('projects.settlement.store');

        Route::get('settlements', [SettlementController::class, 'index'])->name('settlements.index');
        Route::get('settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
        Route::put('settlements/{settlement}', [SettlementController::class, 'update'])->name('settlements.update');
        Route::patch('settlements/{settlement}/paid', [SettlementController::class, 'markPaid'])
            ->name('settlements.paid');
        Route::delete('settlements/{settlement}', [SettlementController::class, 'destroy'])
            ->name('settlements.destroy');

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

        // Bulk filing of older transactions onto Company -> Project -> Person.
        Route::get('transactions/assign', [AssignmentController::class, 'index'])->name('transactions.assign');
        Route::put('transactions/assign', [AssignmentController::class, 'update'])->name('transactions.assign.update');

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

        // Read only on purpose - the activity log has an index and nothing
        // else, so there is no route by which an entry could be changed.
        Route::get('activity', [UserActivityController::class, 'index'])->name('activity.index');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences');
    });
});
