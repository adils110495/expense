<?php

namespace App\Models\Contracts;

/**
 * A record that belongs to a company, and therefore may be hidden from an
 * actor who is not mapped to that company.
 *
 * Implementing this is what puts a model behind EnforceCompanyAccess: every
 * route that resolves one through model binding is checked automatically, so
 * View, Edit, Update and Delete are all covered without a line in any
 * controller. A model that does not implement it is not company-owned - the
 * global lookups (categories, payment-by entries, settings) are shared.
 *
 * The check is deliberately a method on the model rather than a column read
 * in the middleware: a settlement reaches its company through its project,
 * and a person through their assignments, and each is best placed to say so.
 */
interface CompanyScoped
{
    /**
     * May the signed-in actor see and act on this record?
     *
     * Implementations answer through App\Support\CompanyAccess so there is
     * still exactly one place that knows what an actor is entitled to.
     */
    public function accessibleToCurrentActor(): bool;
}
