<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyScopeController;
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
use App\Http\Controllers\Admin\UserAuthController;
use App\Http\Controllers\Admin\UserController;
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

// The second sign-in door. Panel users authenticate on the `web` guard here
// and then use the same /admin screens as an admin, narrowed to the companies
// mapped to them. Admins keep /admin/login below; neither guard affects the
// other, and the two screens cross-link so nobody lands on the wrong one.
Route::middleware('guest:web')->group(function () {
    Route::get('login', [UserAuthController::class, 'showLogin'])->name('user.login');
    Route::post('login', [UserAuthController::class, 'login'])->name('user.login.attempt');
});

Route::prefix('admin')->name('admin.')->group(function () {
    // Guests only - an authenticated admin hitting /admin/login goes to the dashboard.
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    });

    // Everything below is reachable by either an admin or a panel user.
    // company.access then refuses any bound record outside the actor's
    // companies, and admin.only marks the handful of screens that sit above
    // any one company.
    Route::middleware(['auth:admin,web', 'company.access'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // The company selector in the header.
        Route::post('company-scope', CompanyScopeController::class)->name('company-scope');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // The expandable Company -> Project -> Person tree.
        Route::get('hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');

        // Company -> Project -> Person. Declared before the money modules so
        // the hierarchy a transaction points at always exists first.
        //
        // Everyone may browse the companies they are mapped to, but creating
        // and editing one is the admin's: a user who added a company would not
        // be mapped to it and would lose sight of it the moment it saved.
        //
        // middlewareFor() rather than a nested group, so the resource stays a
        // single registration and keeps its own ordering - split in two, the
        // "create" route would be declared after "{company}" and every visit
        // to /companies/create would resolve as a record id instead.
        Route::resource('companies', CompanyController::class)
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'admin.only');
        Route::patch('companies/{company}/toggle', [CompanyController::class, 'toggle'])
            ->middleware('admin.only')->name('companies.toggle');

        // Projects and people are the day-to-day work inside a company, so a
        // user manages their own companies' freely; company.access is what
        // keeps them out of anyone else's.
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

        // Expenses and Credits are list pages only. One combined form under
        // /admin/transactions records both, with the type as a field on it, so
        // every add and edit below lands there instead.
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('credits', [CreditController::class, 'index'])->name('credits.index');

        // Receipts live on the private disk - these routes are the only way in.
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/export/{format}', ExportController::class)->name('transactions.export');

        // Bulk filing of older transactions onto Company -> Project -> Person.
        // Admin only: its whole subject is rows that belong to no company yet,
        // which is precisely what a company-scoped user cannot see.
        Route::middleware('admin.only')->group(function () {
            Route::get('transactions/assign', [AssignmentController::class, 'index'])->name('transactions.assign');
            Route::put('transactions/assign', [AssignmentController::class, 'update'])->name('transactions.assign.update');
        });

        // The single add/edit form for both expenses and credits. Every fixed
        // segment above and below is declared before {transaction}, or a URL
        // like /transactions/create would be read as a record id.
        Route::get('transactions/categories', [TransactionController::class, 'categories'])
            ->name('transactions.categories');
        Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])
            ->name('transactions.destroy');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        // Above any one company, so admin only.
        //
        // Categories and Payment By are shared lists rather than company-owned
        // records - one edit here would land on every company's screens at
        // once. Settings covers the admin's own profile plus the app-wide
        // currency and date format, and the activity log spans every company,
        // so none of the three is a user's to open.
        Route::middleware('admin.only')->group(function () {
            // Panel users and their company mappings - the mapping is the
            // authorisation boundary, so handing it out cannot itself sit
            // inside the boundary.
            // No show, and no destroy: the `users` table has no soft deletes,
            // so removing a row would be irreversible and would take its
            // mappings with it. Deactivating refuses the login just as firmly
            // and leaves the history intact.
            Route::resource('users', UserController::class)
                ->only(['index', 'create', 'store', 'edit', 'update']);
            Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])
                ->name('users.password');

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

            // Read only on purpose - the activity log has an index and nothing
            // else, so there is no route by which an entry could be changed.
            Route::get('activity', [UserActivityController::class, 'index'])->name('activity.index');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
            Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences');
        });
    });
});
