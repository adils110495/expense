<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\CompanyScoped;
use App\Support\CompanyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Top of the hierarchy: Company -> Project -> Person -> Transactions.
 *
 * No totals are stored here. Every credit, expense and balance figure shown
 * for a company is summed from the transactions table at read time, so an
 * edited or deleted transaction is reflected immediately and nothing can
 * drift out of step.
 */
class Company extends Model implements CompanyScoped
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'description', 'status'];

    public function accessibleToCurrentActor(): bool
    {
        return CompanyAccess::allows($this->id);
    }

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The panel users mapped to this company. Admins are not listed here -
     * they reach every company without a mapping.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Narrows to the companies the signed-in actor may see.
     *
     * Null means "no restriction" - that is an admin, and only CompanyAccess
     * is allowed to decide it. Passing an empty array narrows to nothing,
     * which is the correct result for a user mapped to no company at all:
     * failing closed matters more here than showing something.
     *
     * @param  int[]|null  $companyIds
     */
    public function scopeForCompanies(Builder $query, ?array $companyIds): Builder
    {
        return $companyIds === null ? $query : $query->whereIn('companies.id', $companyIds);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('description', 'like', $like));
    }

    /**
     * Distinct head-count per company, in one query rather than one per row.
     *
     * A person on two projects of the same company counts once here, which is
     * what "Total People" on the company dashboard means.
     *
     * @param  int[]  $companyIds  Empty for every company.
     * @return array<int, int>  company id => people
     */
    public static function peopleCounts(array $companyIds = []): array
    {
        return DB::table('projects')
            ->join('project_person', 'project_person.project_id', '=', 'projects.id')
            ->join('people', 'people.id', '=', 'project_person.person_id')
            // The query builder knows nothing about soft deletes, so both
            // sides of the join filter them out by hand.
            ->whereNull('projects.deleted_at')
            ->whereNull('people.deleted_at')
            ->when($companyIds, fn ($q) => $q->whereIn('projects.company_id', $companyIds))
            ->groupBy('projects.company_id')
            ->select('projects.company_id', DB::raw('COUNT(DISTINCT project_person.person_id) as people'))
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->company_id => (int) $row->people])
            ->all();
    }
}
