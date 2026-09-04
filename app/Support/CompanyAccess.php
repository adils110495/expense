<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The company authorisation boundary, in one place.
 *
 * Two kinds of actor sign in to this panel:
 *
 *   Admin (the `admin` guard, /admin/login) - the super admin. Sees every
 *   company, and is the only one who can manage users and their mappings.
 *
 *   User (the `web` guard, /login) - sees exactly the companies mapped to
 *   them in `user_company`, and nothing else.
 *
 * Two different questions get asked of this class, and mixing them up is the
 * bug worth guarding against:
 *
 *   allowedIds()  - what the actor is *entitled* to. This is authorisation.
 *                   Use it for 403 checks on a specific record.
 *   scopeIds()    - what the actor is *currently looking at*, i.e. allowedIds
 *                   narrowed by the company selector in the header. This is a
 *                   view filter. Use it for lists, totals and exports.
 *
 * Both return null to mean "no restriction at all", which only ever happens
 * for an admin. An empty array means "nothing" - a user mapped to no company
 * sees no money, which is the right way for this to fail.
 *
 * Nothing here is cached beyond the current request: when an admin changes a
 * mapping, the user gains or loses that company on their very next request.
 */
class CompanyAccess
{
    /** Where the header's company choice is remembered. */
    public const SESSION_KEY = 'selected_company_id';

    /** Per-request memo so one request does not re-query the pivot. */
    private static ?array $memo = null;

    /* ===================== Who is acting ===================== */

    public static function actor(): Admin|User|null
    {
        return auth('admin')->user() ?? auth('web')->user();
    }

    /** True for the `admin` guard: the super admin, all companies. */
    public static function isAdmin(): bool
    {
        return auth('admin')->check();
    }

    public static function check(): bool
    {
        return self::actor() !== null;
    }

    /* ===================== Authorisation ===================== */

    /**
     * Every company id the actor is entitled to, or null for no restriction.
     *
     * @return int[]|null
     */
    public static function allowedIds(): ?array
    {
        if (self::isAdmin()) {
            return null;
        }

        $user = auth('web')->user();

        if (! $user) {
            // Not signed in at all. Fail closed rather than open - a caller
            // that reaches here outside a session must see nothing.
            return [];
        }

        if (self::$memo === null || (self::$memo['user'] ?? null) !== $user->getKey()) {
            self::$memo = ['user' => $user->getKey(), 'ids' => $user->allowedCompanyIds()];
        }

        return self::$memo['ids'];
    }

    /**
     * May the actor touch this company at all?
     *
     * A null company id means an unfiled record - one with no company on it.
     * Only an admin may see those, so this fails closed for everyone else.
     */
    public static function allows(?int $companyId): bool
    {
        $allowed = self::allowedIds();

        if ($allowed === null) {
            return true;
        }

        return $companyId !== null && in_array($companyId, $allowed, true);
    }

    /**
     * @param  int[]  $companyIds
     */
    public static function allowsAny(array $companyIds): bool
    {
        $allowed = self::allowedIds();

        if ($allowed === null) {
            return true;
        }

        return (bool) array_intersect($companyIds, $allowed);
    }

    /** 403s unless the actor may touch this company. */
    public static function authorize(?int $companyId): void
    {
        abort_unless(self::allows($companyId), 403, 'You do not have access to this company.');
    }

    /* ===================== The header selector ===================== */

    /**
     * The company chosen in the header, or null for "All companies".
     *
     * Re-validated against the *current* mapping on every read, so a company
     * removed from a user a moment ago cannot go on scoping their screens
     * just because the id is still sitting in their session.
     */
    public static function selectedId(): ?int
    {
        $id = session(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        if (! self::allows((int) $id)) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        return (int) $id;
    }

    /**
     * Records a choice from the header. An id the actor is not entitled to is
     * refused rather than quietly ignored - it can only arrive by hand.
     */
    public static function select(?int $companyId): void
    {
        if ($companyId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        self::authorize($companyId);

        session([self::SESSION_KEY => $companyId]);
    }

    /* ===================== The view filter ===================== */

    /**
     * The ids every list, total and export should filter by: allowedIds()
     * narrowed by the header selection. Null means no restriction.
     *
     * Note an admin who picks one company gets [id] here but still keeps
     * allowedIds() === null, so their screens narrow while their authority
     * does not - opening a record from another company still works.
     *
     * @return int[]|null
     */
    public static function scopeIds(): ?array
    {
        $selected = self::selectedId();

        if ($selected !== null) {
            return [$selected];
        }

        return self::allowedIds();
    }

    /**
     * The companies to offer in the header selector: every company for an
     * admin, only the mapped ones for a user. Deactivated companies are
     * included - their history still has to be reachable.
     */
    public static function selectable(): Collection
    {
        $allowed = self::allowedIds();

        return Company::query()
            ->forCompanies($allowed)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    /** True when there is a real choice to offer in the header. */
    public static function hasChoice(): bool
    {
        return self::selectable()->count() > 1;
    }

    /**
     * Forgets the per-request memo. Only the tests and the queue worker need
     * this; a normal web request builds a fresh container each time.
     */
    public static function flush(): void
    {
        self::$memo = null;
    }
}
