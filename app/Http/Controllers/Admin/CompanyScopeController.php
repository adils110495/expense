<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CompanyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The company selector in the header.
 *
 * Records which company the actor is currently looking at - or "All
 * companies", which means every company they are mapped to. The value is kept
 * in the session and re-checked against the live mapping on every read, so it
 * is a view preference and never a grant of access: CompanyAccess::select()
 * refuses an id the actor is not entitled to, and even a value that slipped
 * in is discarded the moment the mapping no longer allows it.
 */
class CompanyScopeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            // Empty string is "All companies"; anything else has to be a real
            // company, and CompanyAccess then decides whether it is theirs.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        CompanyAccess::select($request->filled('company_id') ? $request->integer('company_id') : null);

        // Straight back where they were, so switching company re-renders the
        // screen they are on rather than sending them to the dashboard.
        return back();
    }
}
