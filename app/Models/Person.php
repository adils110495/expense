<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
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
class Person extends Model
{
    use SoftDeletes, LogsActivity;

    // Eloquent would look for a "persons" table.
    protected $table = 'people';

    /** Families of notification a partner can opt out of, per channel. */
    public const NOTIFICATION_GROUPS = [
        'expense' => 'Expense notifications',
        'credit' => 'Credit notifications',
        'settlement' => 'Settlement notifications',
        'summary' => 'Monthly summary',
    ];

    protected $fillable = [
        'name', 'email', 'phone', 'designation', 'status', 'notes',
        'whatsapp_number', 'whatsapp_enabled', 'email_enabled', 'notification_prefs',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'notification_prefs' => 'array',
        ];
    }

    /**
     * Whether this person wants a given event on a given channel.
     *
     * Three things must all agree: the channel is on for them, the family is
     * not opted out, and there is somewhere to send it. Anything unset counts
     * as opted in - a partner who has never touched their preferences should
     * still hear about money, and a newly added event should not arrive
     * silently switched off for everyone.
     */
    public function acceptsNotification(string $channel, string $group): bool
    {
        $channelOn = $channel === 'whatsapp' ? $this->whatsapp_enabled : $this->email_enabled;

        if (! $channelOn || blank($this->contactFor($channel))) {
            return false;
        }

        return (bool) (($this->notification_prefs[$channel][$group] ?? true));
    }

    /** Where a message on this channel would go, or null if nowhere. */
    public function contactFor(string $channel): ?string
    {
        // Falls back to the phone number: the two are usually the same, and a
        // missed message is worse than sending to the number already on file.
        return $channel === 'whatsapp'
            ? ($this->whatsapp_number ?: $this->phone)
            : $this->email;
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
