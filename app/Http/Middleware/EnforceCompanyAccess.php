<?php

namespace App\Http\Middleware;

use App\Models\Contracts\CompanyScoped;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Direct URL protection, applied to every route at once.
 *
 * Runs after route-model binding has resolved the URL's ids into models, and
 * refuses the request if any of them belongs to a company the signed-in actor
 * is not mapped to. Because it looks at the *bound models* rather than at the
 * route names, it covers show, edit, update, destroy, the attachment
 * downloads and anything added later, with no per-controller checks to keep
 * in step and none to forget.
 *
 * This is the guard for one named record. Lists, totals and exports are a
 * separate problem - they are filtered by CompanyAccess::scopeIds() in the
 * queries themselves, because there is no bound model to check.
 *
 * A 403 is deliberate rather than a 404: within the panel these ids are not
 * secret, and telling someone they lack access is more useful than pretending
 * the record does not exist.
 */
class EnforceCompanyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $parameter) {
                if ($parameter instanceof CompanyScoped && ! $parameter->accessibleToCurrentActor()) {
                    abort(403, 'You do not have access to this company.');
                }
            }
        }

        return $next($request);
    }
}
