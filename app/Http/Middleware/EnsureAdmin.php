<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the super admin.
 *
 * Panel users share almost every screen with the admin, scoped to their own
 * companies. What they do not share is the machinery that sits above any one
 * company: managing users and their company mappings, the shared category and
 * payment-by lists, the app-wide preferences, the activity log, and the bulk
 * assign screen, whose whole subject is transactions that belong to no
 * company yet.
 *
 * Company-level access is a separate question, answered by
 * EnforceCompanyAccess; this one is only about which of the two guards is
 * signed in.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(auth('admin')->check(), 403, 'This section is for administrators only.');

        return $next($request);
    }
}
