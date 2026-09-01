<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Person;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Totals for every level of Company -> Project -> Person.
 *
 * Nothing here is stored. Each method starts from a transaction query and
 * sums it in SQL, so adding, editing, moving or deleting a transaction is
 * reflected on every level at once and no running total can drift.
 *
 * The whole tree costs a fixed handful of queries however deep it is: one
 * grouped SUM over transactions, plus one read per level for the names.
 */
class HierarchyReport
{
    /**
     * @param  Builder  $base  An already filtered transaction query - date
     *                         range, type, category and so on. Every total
     *                         this class returns respects it.
     */
    public function __construct(private readonly Builder $base) {}

    public static function forRange(?string $from = null, ?string $to = null): self
    {
        return new self(Transaction::query()->between($from, $to));
    }

    /* ===================== Building blocks ===================== */

    /** An empty money bucket - the shape every total in this class uses. */
    public static function blank(): array
    {
        return [
            'credit' => '0.00',
            'expense' => '0.00',
            'balance' => '0.00',
            'credit_count' => 0,
            'expense_count' => 0,
            'count' => 0,
        ];
    }

    /**
     * Folds one grouped SUM row into a bucket. Balance is recomputed from the
     * two running sides rather than accumulated, so it can never disagree.
     */
    private static function fold(array $bucket, string $type, string $total, int $count): array
    {
        if (! array_key_exists($type, $bucket)) {
            return $bucket;
        }

        $bucket[$type] = bcadd($bucket[$type], $total === '' ? '0' : $total, 2);
        $bucket[$type.'_count'] += $count;
        $bucket['count'] += $count;
        $bucket['balance'] = bcsub($bucket['credit'], $bucket['expense'], 2);

        return $bucket;
    }

    /**
     * SUM(amount) and COUNT(*) grouped by the given columns plus type.
     *
     * @param  string[]  $columns
     */
    private function grouped(array $columns): Collection
    {
        return (clone $this->base)
            ->toBase()
            ->select([
                ...$columns,
                'type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as row_count'),
            ])
            ->groupBy([...$columns, 'type'])
            ->get();
    }

    /**
     * Totals keyed by the id in $column - 'company_id', 'project_id' or
     * 'person_id'. Rows with a NULL key are collected under key 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public function totalsBy(string $column): array
    {
        $totals = [];

        foreach ($this->grouped([$column]) as $row) {
            $key = (int) ($row->{$column} ?? 0);
            $totals[$key] ??= self::blank();
            $totals[$key] = self::fold($totals[$key], $row->type, (string) $row->total, (int) $row->row_count);
        }

        return $totals;
    }

    /**
     * Totals for one person on one project, keyed "projectId:personId".
     *
     * A person on two projects has two separate balances here; their overall
     * balance is totalsBy('person_id').
     *
     * @return array<string, array<string, mixed>>
     */
    public function personTotalsPerProject(): array
    {
        $totals = [];

        foreach ($this->grouped(['project_id', 'person_id']) as $row) {
            $key = (int) ($row->project_id ?? 0).':'.(int) ($row->person_id ?? 0);
            $totals[$key] ??= self::blank();
            $totals[$key] = self::fold($totals[$key], $row->type, (string) $row->total, (int) $row->row_count);
        }

        return $totals;
    }

    /* ===================== The tree ===================== */

    /**
     * The full Company -> Project -> Person tree with a balance on every node.
     *
     * People appear under a project when they are assigned to it or when they
     * carry money on it - so removing someone from a project never hides the
     * transactions already booked against them.
     *
     * @param  int|null  $companyId  Limit to one company (its own dashboard).
     * @return array<int, array<string, mixed>>
     */
    public function tree(?int $companyId = null): array
    {
        $companyTotals = $this->totalsBy('company_id');
        $projectTotals = $this->totalsBy('project_id');
        $personTotals = $this->personTotalsPerProject();

        $companies = Company::query()
            ->when($companyId, fn (Builder $q) => $q->where('id', $companyId))
            ->with(['projects' => fn ($q) => $q->orderBy('name')->with('people')])
            ->orderBy('name')
            ->get();

        // People holding money on a project they are no longer assigned to.
        $strays = $this->strayPeople($companies);

        $tree = [];

        foreach ($companies as $company) {
            $projects = [];

            foreach ($company->projects as $project) {
                $people = [];

                foreach ($this->peopleOn($project, $strays) as $person) {
                    $people[] = [
                        'id' => $person->id,
                        'name' => $person->name,
                        'designation' => $person->designation,
                        'status' => (bool) $person->status,
                        'assigned' => $project->people->contains('id', $person->id),
                        'totals' => $personTotals[$project->id.':'.$person->id] ?? self::blank(),
                    ];
                }

                $projects[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => (bool) $project->status,
                    'people' => $people,
                    'totals' => $projectTotals[$project->id] ?? self::blank(),
                ];
            }

            $tree[] = [
                'id' => $company->id,
                'name' => $company->name,
                'status' => (bool) $company->status,
                'projects' => $projects,
                'totals' => $companyTotals[$company->id] ?? self::blank(),
            ];
        }

        return $tree;
    }

    /**
     * People with transactions on a project but no assignment to it, keyed by
     * project id. One query for the pairs, one for the names.
     *
     * @return array<int, Collection>
     */
    private function strayPeople(Collection $companies): array
    {
        $projectIds = $companies->pluck('projects')->flatten(1)->pluck('id');

        if ($projectIds->isEmpty()) {
            return [];
        }

        $pairs = (clone $this->base)
            ->toBase()
            ->select('project_id', 'person_id')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('person_id')
            ->distinct()
            ->get();

        $assigned = [];

        foreach ($companies as $company) {
            foreach ($company->projects as $project) {
                $assigned[$project->id] = $project->people->pluck('id')->all();
            }
        }

        $missing = [];

        foreach ($pairs as $pair) {
            $projectId = (int) $pair->project_id;

            if (! in_array((int) $pair->person_id, $assigned[$projectId] ?? [], true)) {
                $missing[$projectId][] = (int) $pair->person_id;
            }
        }

        if (! $missing) {
            return [];
        }

        // withTrashed: a deleted person's past money still has to be shown.
        $people = Person::withTrashed()
            ->whereIn('id', collect($missing)->flatten()->unique()->all())
            ->get()
            ->keyBy('id');

        return collect($missing)
            ->map(fn (array $ids) => collect($ids)
                ->map(fn (int $id) => $people->get($id))
                ->filter()
                ->values())
            ->all();
    }

    /** Assigned people plus any strays, in name order. */
    private function peopleOn(Project $project, array $strays): Collection
    {
        return $project->people
            ->concat($strays[$project->id] ?? [])
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /* ===================== Flat report tables ===================== */

    /**
     * One row per company that has activity in the period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byCompany(): array
    {
        $totals = $this->totalsBy('company_id');

        $rows = Company::query()
            ->whereIn('id', $this->realIds($totals))
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'totals' => $totals[$company->id] ?? self::blank(),
            ])
            ->all();

        return $this->sortByExpense($rows);
    }

    /**
     * One row per project, with the company it belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byProject(): array
    {
        $totals = $this->totalsBy('project_id');

        $rows = Project::query()
            ->whereIn('id', $this->realIds($totals))
            ->with('company')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'company' => $project->company?->name,
                'company_id' => $project->company_id,
                'totals' => $totals[$project->id] ?? self::blank(),
            ])
            ->all();

        return $this->sortByExpense($rows);
    }

    /**
     * One row per person, across every project they work on.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byPerson(): array
    {
        $totals = $this->totalsBy('person_id');

        $rows = Person::query()
            ->whereIn('id', $this->realIds($totals))
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'designation' => $person->designation,
                'totals' => $totals[$person->id] ?? self::blank(),
            ])
            ->all();

        return $this->sortByExpense($rows);
    }

    /**
     * The ids in a totals map, dropping the 0 bucket that collects rows with
     * no company/project/person - there is no record to name for those.
     *
     * @return int[]
     */
    private function realIds(array $totals): array
    {
        return array_values(array_filter(array_keys($totals), fn (int $id) => $id > 0));
    }

    /** Highest expense first; ties fall back to the alphabetical order above. */
    private function sortByExpense(array $rows): array
    {
        usort($rows, fn (array $a, array $b) => bccomp($b['totals']['expense'], $a['totals']['expense'], 2)
            ?: bccomp($b['totals']['credit'], $a['totals']['credit'], 2));

        return $rows;
    }
}
