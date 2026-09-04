<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A panel user, signing in at /login.
 *
 * Distinct from Admin: an admin signs in at /admin/login and sees everything,
 * whereas a user sees exactly the companies mapped to them in `user_company`
 * and nothing else. That mapping is an authorisation boundary, not a display
 * preference - see App\Support\CompanyAccess, which is the only place the
 * allowed company ids are worked out.
 */
#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * The companies this user may see. Every company-scoped query in the panel
     * ultimately comes back to this relation.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company')
            ->withTimestamps()
            ->orderBy('companies.name');
    }

    /**
     * Ids only, read fresh from the pivot.
     *
     * Deliberately not cached on the model or in the session: when an admin
     * changes a mapping the user must gain or lose that company on their very
     * next request, not whenever a cache happens to expire.
     *
     * @return int[]
     */
    public function allowedCompanyIds(): array
    {
        return $this->companies()
            ->pluck('companies.id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
            ->orWhere('email', 'like', $like));
    }

    /** Users mapped to a given company - the filter on the users list. */
    public function scopeInCompany(Builder $query, mixed $companyId): Builder
    {
        return $companyId
            ? $query->whereHas('companies', fn (Builder $q) => $q->where('companies.id', $companyId))
            : $query;
    }
}
