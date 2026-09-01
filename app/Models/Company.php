<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
class Company extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'description', 'status'];

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
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
