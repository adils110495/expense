<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\UserActivity;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The activity log, read only.
 *
 * There is deliberately no store, update or destroy here and no route to one.
 * The log is the record of what happened; being able to edit or delete an
 * entry from the screen that displays it would defeat the point of keeping it.
 */
class UserActivityController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'all');

        $activities = UserActivity::query()
            ->ofAction($request->query('action'))
            ->ofTable($request->query('table_name'))
            // created_at is a timestamp, so the closing day is matched by date
            // rather than by an exact midnight boundary.
            ->when($range->from, fn ($q) => $q->whereDate('created_at', '>=', $range->from))
            ->when($range->to, fn ($q) => $q->whereDate('created_at', '<=', $range->to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity.index', [
            'activities' => $activities,
            'range' => $range,
            // The tables that actually appear in the log, so the filter never
            // offers a value that would return nothing.
            'tables' => UserActivity::query()
                ->distinct()
                ->orderBy('table_name')
                ->pluck('table_name'),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }
}
