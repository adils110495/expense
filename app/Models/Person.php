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

/**
 * A person / employee. People exist independently of projects and are
 * assigned to one or many through the project_person pivot, so the same
 * person can carry credits and expenses across several projects.
 */
class Person extends Model implements CompanyScoped
{
    use SoftDeletes, LogsActivity;

    // Eloquent would look for a "persons" table.
    protected $table = 'people';

    /**
     * Mirrors scopeForCompanies: reachable through any project assignment in
     * one of your companies, and reachable by everyone while unassigned.
     */
    public function accessibleToCurrentActor(): bool
    {
        $companyIds = $this->projects()
            ->pluck('projects.company_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $companyIds === [] || CompanyAccess::allowsAny($companyIds);
    }

    protected $fillable = [
        'name', 'email', 'phone', 'designation', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function projects(): BelongsToMany
    {
        // Named explicitly for the same reason as Project::people() - the
        // convention would derive "person_project".
        return $this->belongsToMany(Project::class, 'project_person')
            ->withTimestamps()
            ->orderBy('projects.name');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Settlements this person is on either side of. */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'from_person_id');
    }

    public function settlementsReceived(): HasMany
    {
        return $this->hasMany(Settlement::class, 'to_person_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Narrows to the people the signed-in actor may see.
     *
     * People carry no company of their own, so the boundary is drawn through
     * their project assignments: you can see someone if they work on one of
     * your companies' projects. Somebody assigned to nothing yet is visible to
     * everyone - they hold no company's data, and hiding them would make it
     * impossible to put a newly added person onto your own project.
     *
     * @param  int[]|null  $companyIds  Null for an admin: no restriction.
     */
    public function scopeForCompanies(Builder $query, ?array $companyIds): Builder
    {
        if ($companyIds === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereHas('projects', fn (Builder $p) => $p->whereIn('projects.company_id', $companyIds))
            ->orWhereDoesntHave('projects'));
    }

    /** People assigned to a given project - the source for the person dropdown. */
    public function scopeOnProject(Builder $query, mixed $projectId): Builder
    {
        return $projectId
            ? $query->whereHas('projects', fn (Builder $q) => $q->where('projects.id', $projectId))
            : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('designation', 'like', $like));
    }
}
