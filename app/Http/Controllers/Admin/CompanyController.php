<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\FinanceReport;
use App\Services\HierarchyReport;
use App\Services\SettlementEngine;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $companies = Company::query()
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (bool) $request->query('status')))
            ->withCount(['projects', 'transactions'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Totals for the page's companies only, in one grouped query.
        $report = HierarchyReport::forRange($range->from, $range->to);

        return view('admin.companies.index', [
            'companies' => $companies,
            'totals' => $report->totalsBy('company_id'),
            'peopleCounts' => Company::peopleCounts($companies->pluck('id')->all()),
            'range' => $range,
        ]);
    }

    public function create(): View
    {
        return view('admin.companies.form', [
            'company' => new Company(['status' => true]),
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = Company::create($request->validated());

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    /**
     * The company dashboard: summary across the whole company, then every
     * project underneath it, then the expandable tree.
     */
    public function show(Request $request, Company $company): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $base = Transaction::query()
            ->between($range->from, $range->to)
            ->where('company_id', $company->id);

        $report = new HierarchyReport($base);

        $projects = $company->projects()->withCount('people')->orderBy('name')->get();

        // Company-level settlement is only ever a roll-up for visibility.
        // Each project settles on its own numbers - Project 1's balances never
        // net off against Project 2's. Scoped to the page's period, like every
        // other figure on it.
        $plans = SettlementEngine::forProjects($projects, $range->from, $range->to);

        return view('admin.companies.show', [
            'company' => $company,
            'range' => $range,
            'summary' => (new FinanceReport($range))->summary(clone $base),
            'projectTotals' => $report->totalsBy('project_id'),
            'projects' => $projects,
            'settlementPlans' => $plans,
            'settlementTotal' => array_sum(array_column($plans, 'to_settle')),
            'peopleCount' => Company::peopleCounts([$company->id])[$company->id] ?? 0,
            'tree' => $report->tree($company->id),
            'recent' => (clone $base)
                ->with(['category', 'project', 'person'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.form', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Activate / deactivate. A deactivated company keeps every transaction it
     * already has - it simply stops being offered on the add forms.
     */
    public function toggle(Company $company): RedirectResponse
    {
        $company->update(['status' => ! $company->status]);

        return back()->with(
            'success',
            'Company '.($company->status ? 'activated' : 'deactivated').'.'
        );
    }

    /**
     * Deleting is only allowed while nothing hangs off the company. Once it
     * has projects or transactions, deactivating is the honest move - it
     * keeps the history intact and the totals correct.
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->projects()->exists() || $company->transactions()->exists()) {
            return back()->with(
                'error',
                'This company still has projects or transactions. Deactivate it instead.'
            );
        }

        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('success', 'Company deleted successfully.');
    }
}
