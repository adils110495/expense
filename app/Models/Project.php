<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A project always belongs to exactly one company, and its people are the
 * only people an expense or credit on this project may be booked against.
 */
class Project extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'company_id', 'name', 'description', 'start_date', 'end_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function people(): BelongsToMany
    {
        // The pivot is named explicitly: Eloquent's convention sorts the two
        // model names alphabetically and would look for "person_project".
        return $this->belongsToMany(Person::class, 'project_person')
            ->withTimestamps()
            ->orderBy('people.name');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Recorded partner-to-partner payments on this project. */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOfCompany(Builder $query, mixed $companyId): Builder
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like)));
    }
}
