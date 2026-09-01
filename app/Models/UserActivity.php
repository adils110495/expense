<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The activity log.
 *
 * Append only by design: the model refuses updates and deletes outright, so
 * the trail cannot be rewritten from anywhere in the app, and the admin panel
 * offers no route to try.
 *
 * Writing a log entry must never be the reason a real operation fails, so
 * record() swallows its own errors into the application log rather than
 * letting them bubble out into the user's request.
 */
class UserActivity extends Model
{
    public const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'force_deleted' => 'Permanently deleted',
        'restored' => 'Restored',
        'assigned' => 'Assigned',
        'unassigned' => 'Unassigned',
        'login' => 'Signed in',
        'logout' => 'Signed out',
    ];

    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'table_name',
        'record_id', 'description', 'ip_address',
    ];

    /**
     * Nothing edits or removes an entry. Returning false from these events
     * cancels the operation, so even a stray update() elsewhere is a no-op
     * rather than a silent rewrite of history.
     */
    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? Str::headline($this->action);
    }

    /* ===================== Writing ===================== */

    /**
     * Records one entry. The acting admin, and the address they acted from,
     * are read from the current request; in a console context both are simply
     * absent and the entry is attributed to the system.
     */
    public static function record(
        string $action,
        string $table,
        ?int $recordId = null,
        ?string $description = null,
    ): void {
        try {
            $admin = auth('admin')->user();

            self::create([
                'admin_id' => $admin?->id,
                'admin_name' => $admin?->name ?? 'System',
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'description' => $description ? Str::limit($description, 250) : null,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Never break the operation being logged.
            Log::warning('Could not write activity log entry: '.$e->getMessage(), [
                'action' => $action,
                'table' => $table,
                'record_id' => $recordId,
            ]);
        }
    }

    /** Records an entry about a model, naming it as helpfully as it can. */
    public static function forModel(string $action, Model $model): void
    {
        self::record($action, $model->getTable(), $model->getKey(), self::describe($model));
    }

    /**
     * A short human label for a row: its class, plus whatever it calls itself.
     * Deliberately never the changed values - an audit log should say what was
     * touched, not carry copies of the data, which may be sensitive.
     */
    private static function describe(Model $model): string
    {
        $label = Str::headline(class_basename($model));

        foreach (['name', 'title'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return $label.': '.$model->getAttribute($attribute);
            }
        }

        return $label.' #'.$model->getKey();
    }

    /* ===================== Reading ===================== */

    public function scopeOfAction(Builder $query, ?string $action): Builder
    {
        return $action ? $query->where('action', $action) : $query;
    }

    public function scopeOfTable(Builder $query, ?string $table): Builder
    {
        return $table ? $query->where('table_name', $table) : $query;
    }
}
