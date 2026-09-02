<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Project;
use App\Models\Setting;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\DateRange;
use App\Support\NotificationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The notification dashboard and its log.
 *
 * Read mostly: the only writes are retrying something that failed and firing
 * a bulk reminder, both of which go through the queue like everything else.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'this_month');

        $filtered = NotificationLog::query()
            ->search($request->query('q'))
            ->ofChannel($request->query('channel'))
            ->ofStatus($request->query('status'))
            ->ofEvent($request->query('event'))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($range->from, fn ($q) => $q->whereDate('created_at', '>=', $range->from))
            ->when($range->to, fn ($q) => $q->whereDate('created_at', '<=', $range->to));

        return view('admin.notifications.index', [
            'logs' => (clone $filtered)
                ->with(['person', 'project'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            // Counted over the filtered set, so the cards describe what is on
            // screen rather than the whole table.
            'summary' => $this->summary(clone $filtered),
            'range' => $range,
            'projects' => Project::with('company')->orderBy('name')->get(),
            'events' => NotificationTemplate::EVENTS,
            'channels' => [
                'whatsapp' => NotificationConfig::ready('whatsapp'),
                'email' => NotificationConfig::ready('email'),
            ],
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    /**
     * Counts per status in one grouped query, rather than one count per card.
     *
     * @return array<string, int>
     */
    private function summary($query): array
    {
        $rows = $query->toBase()
            ->select('channel', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('channel', 'status')
            ->get();

        $summary = [
            'total' => 0, 'whatsapp' => 0, 'email' => 0,
            'sent' => 0, 'delivered' => 0, 'read' => 0,
            'failed' => 0, 'pending' => 0,
        ];

        foreach ($rows as $row) {
            $count = (int) $row->total;

            $summary['total'] += $count;
            $summary[$row->channel] += $count;

            if (array_key_exists($row->status, $summary)) {
                $summary[$row->status] += $count;
            }
        }

        // Anything the provider accepted, however far it then got.
        $summary['succeeded'] = $summary['sent'] + $summary['delivered'] + $summary['read'];

        return $summary;
    }

    /**
     * Puts a failed notification back on the queue.
     *
     * The row is reset to pending rather than duplicated, so the log keeps one
     * line per message with a growing attempt count instead of a pile of
     * near-identical entries.
     */
    public function retry(NotificationLog $log): RedirectResponse
    {
        if (! in_array($log->status, ['failed', 'bounced'], true)) {
            return back()->with('error', 'Only a failed notification can be retried.');
        }

        if (! NotificationConfig::ready($log->channel)) {
            return back()->with('error', ucfirst($log->channel).' is not configured, so there is nothing to retry onto.');
        }

        $log->update(['status' => 'pending', 'error' => null]);

        SendNotification::dispatch($log->id);

        return back()->with('success', 'Queued for another attempt.');
    }

    /** Stops a pending notification from being sent at all. */
    public function cancel(NotificationLog $log): RedirectResponse
    {
        if ($log->status !== 'pending') {
            return back()->with('error', 'Only a pending notification can be cancelled.');
        }

        $log->update(['status' => 'cancelled']);

        return back()->with('success', 'Notification cancelled.');
    }

    /**
     * Settlement reminders for every project at once - the "remind everyone"
     * the spec asks for, built per recipient so nobody sees another partner's
     * figures.
     */
    public function remindAll(): RedirectResponse
    {
        if (! NotificationConfig::ready('whatsapp') && ! NotificationConfig::ready('email')) {
            return back()->with('error', 'Neither WhatsApp nor email is configured yet.');
        }

        $queued = 0;

        foreach (Project::active()->with('company')->get() as $project) {
            $queued += $this->notifications->settlementReminders($project, force: true);
        }

        return $queued > 0
            ? back()->with('success', 'Queued '.$queued.' settlement reminder(s) across all active projects.')
            : back()->with('error', 'Nothing outstanding, or nobody has a usable contact.');
    }
}
