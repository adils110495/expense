<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettlementRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Settlement;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\SettlementEngine;
use App\Support\DateRange;
use App\Support\NotificationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /**
     * A project's settlement: the equal share, every partner's position, and
     * the exact list of who pays whom.
     *
     * The plan is recalculated here on every request rather than read from
     * anywhere, which is what keeps it correct after an expense, credit or
     * partner changes.
     */
    public function project(Request $request, Project $project): View
    {
        $project->load('company');

        // Defaults to all time - settling up is normally about everything to
        // date - but the period filter narrows it like every other page.
        $range = DateRange::fromRequest($request, 'all');

        $plan = SettlementEngine::forProject($project, $range->from, $range->to);

        return view('admin.settlements.project', [
            'project' => $project,
            'range' => $range,
            'plan' => $plan,
            // Transfers the admin has already written down, so a suggested
            // payment can show as recorded instead of being offered twice.
            'recorded' => Settlement::query()
                ->where('project_id', $project->id)
                ->with(['from', 'to', 'attachments'])
                ->orderByDesc('created_at')
                ->get(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    /** Settlement history across every project. */
    public function index(Request $request): View
    {
        $settlements = Settlement::query()
            ->ofProject($request->query('project_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('person_id'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('from_person_id', $request->query('person_id'))
                    ->orWhere('to_person_id', $request->query('person_id'));
            }))
            ->with(['from', 'to', 'project.company'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.settlements.index', [
            'settlements' => $settlements,
            'projects' => Project::with('company')->orderBy('name')->get(),
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    public function show(Settlement $settlement): View
    {
        $settlement->load(['from', 'to', 'project.company', 'creator', 'attachments']);

        return view('admin.settlements.show', [
            'settlement' => $settlement,
            'dateFormat' => Setting::get('date_format') ?? 'd M Y',
        ]);
    }

    /**
     * Records one of the suggested transfers so it can be tracked and marked
     * paid. Recording alone moves no money - only paid_amount does that.
     */
    public function store(SettlementRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        $settlement = Settlement::create([
            ...$this->normalise($data),
            'project_id' => $project->id,
            'from_person_id' => $data['from_person_id'],
            'to_person_id' => $data['to_person_id'],
            'kind' => $data['kind'],
            'created_by' => $request->user('admin')->id,
        ]);

        $this->syncAttachments($request, $settlement);

        return back()->with('success', 'Settlement recorded.');
    }

    public function update(SettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        $wasPaid = $settlement->status === 'paid';

        $settlement->update($this->normalise($request->validated()));

        $this->syncAttachments($request, $settlement);

        // Only on the transition into paid, so editing a note on an already
        // settled payment does not tell everyone about it again.
        if (! $wasPaid && $settlement->status === 'paid') {
            $this->notifications->settlementPaid($settlement);
        }

        return back()->with('success', 'Settlement updated.');
    }

    /**
     * Removes the receipts the user ticked, then stores any new uploads.
     *
     * Files go on the private disk and are reachable only through the
     * authenticated attachment route - the same pipeline expenses use.
     */
    private function syncAttachments(SettlementRequest $request, Settlement $settlement): void
    {
        $removeIds = $request->input('remove_attachments', []);

        if ($removeIds) {
            // Scoped to this settlement, so an id belonging to another record
            // cannot be passed in to delete someone else's file.
            $settlement->attachments()->whereIn('id', $removeIds)->get()
                ->each(fn (Attachment $attachment) => $attachment->deleteWithFile());
        }

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('settlements/'.$settlement->id, 'local');

            $settlement->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                // Kept for display and the download filename only - never used
                // to build a storage path.
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user('admin')->id,
            ]);
        }
    }

    /**
     * A one-click "mark paid" for the common case, so the admin does not have
     * to open a form to tick off a transfer that was paid in full.
     */
    public function markPaid(Settlement $settlement): RedirectResponse
    {
        $settlement->update([
            'status' => 'paid',
            'paid_amount' => $settlement->amount,
            'settled_on' => $settlement->settled_on ?? now()->toDateString(),
        ]);

        $this->notifications->settlementPaid($settlement);

        return back()->with('success', 'Settlement marked as paid.');
    }

    /**
     * Sends one partner their settlement reminder on demand, on the channel
     * the admin picked. Preference switches are overridden - an admin asking
     * for this has decided - but a partner with no number or address on that
     * channel still cannot be reached.
     */
    public function notify(Request $request, Settlement $settlement): RedirectResponse
    {
        $channel = $request->input('channel');

        if (! in_array($channel, ['whatsapp', 'email'], true)) {
            return back()->with('error', 'Choose WhatsApp or email.');
        }

        if (! NotificationConfig::ready($channel)) {
            return back()->with('error', ucfirst($channel).' is not switched on and configured yet.');
        }

        $sent = $this->notifications->manualSettlement($settlement, $channel);

        return $sent
            ? back()->with('success', 'Queued a '.$channel.' message to '.$settlement->from?->name.'.')
            : back()->with('error', $settlement->from?->name.' has no usable '.$channel.' contact.');
    }

    /**
     * Reminds everyone on a project who owes or is owed.
     *
     * Each message is built for its recipient from the current plan, so no
     * one is sent another partner's figures.
     */
    public function remindAll(Project $project): RedirectResponse
    {
        if (! NotificationConfig::ready('whatsapp') && ! NotificationConfig::ready('email')) {
            return back()->with('error', 'Neither WhatsApp nor email is configured yet.');
        }

        $queued = $this->notifications->settlementReminders($project, force: true);

        return $queued > 0
            ? back()->with('success', 'Queued '.$queued.' settlement reminder(s).')
            : back()->with('error', 'Nothing to remind anyone about, or nobody has a usable contact.');
    }

    public function destroy(Settlement $settlement): RedirectResponse
    {
        $settlement->delete();

        return back()->with('success', 'Settlement removed. The plan has been recalculated.');
    }

    /**
     * Keeps paid_amount and status telling the same story - the engine counts
     * paid_amount, so a "paid" row with nothing paid would silently settle
     * nothing while looking settled.
     */
    private function normalise(array $data): array
    {
        $status = $data['status'];

        $paid = match ($status) {
            'paid' => $data['amount'],
            'pending', 'cancelled' => '0.00',
            default => $data['paid_amount'] ?? '0.00',
        };

        return [
            'amount' => $data['amount'],
            'paid_amount' => $paid,
            'status' => $status,
            'payment_method' => $data['payment_method'] ?? null,
            'location' => $data['location'] ?? null,
            // A settled row wants a date; anything unpaid keeps whatever was
            // entered, which may be nothing.
            'settled_on' => $data['settled_on']
                ?? ($status === 'paid' ? now()->toDateString() : null),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
