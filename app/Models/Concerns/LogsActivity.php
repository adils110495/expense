<?php

namespace App\Models\Concerns;

use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an activity entry whenever a model is created, changed or removed.
 *
 * Hooked onto Eloquent's own events, so every controller gets logging without
 * a single line in any of them.
 *
 * The one thing it cannot see is a mass update - `Model::where(...)->update()`
 * goes straight to SQL and fires no events. The few places that do that log
 * the action themselves; see AssignmentController.
 */
trait LogsActivity
{
    /** Columns whose change on its own is not worth an entry. */
    private static array $activityNoise = ['remember_token', 'updated_at'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => UserActivity::forModel('created', $model));

        // Only fires when something actually changed - Eloquent skips the
        // update entirely when nothing is dirty.
        static::updated(function (Model $model) {
            // A remember-me token being cycled is bookkeeping, not an edit.
            // Logging it would put an "Updated Admin" beside every sign-in.
            $changed = array_diff(array_keys($model->getChanges()), self::$activityNoise);

            if ($changed) {
                UserActivity::forModel('updated', $model);
            }
        });

        static::deleted(function (Model $model) {
            $forced = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

            UserActivity::forModel($forced ? 'force_deleted' : 'deleted', $model);
        });

        // Only soft-deleting models can be restored; the event does not exist
        // on the others.
        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => UserActivity::forModel('restored', $model));
        }
    }
}
