<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettlementRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Settlement;
use App\Services\SettlementEngine;
use App\Support\CompanyAccess;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettlementController extends Controller
{
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
            ->forCompanies(CompanyAccess::scopeIds())
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
            'projects' => Project::forCompanies(CompanyAccess::allowedIds())
                ->with('company')->orderBy('name')->get(),
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
            // Null for a panel user: the column is a foreign key into admins, so
            // only an admin can be named here. Who actually did it is recorded
            // either way in the activity log, which knows both guards.
            'created_by' => auth('admin')->id(),
        ]);

        $this->syncAttachments($request, $settlement);

        return back()->with('success', 'Settlement recorded.');
    }

    public function update(SettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        $settlement->update($this->normalise($request->validated()));

        $this->syncAttachments($request, $settlement);

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
                'uploaded_by' => auth('admin')->id(),
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

        return back()->with('success', 'Settlement marked as paid.');
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
