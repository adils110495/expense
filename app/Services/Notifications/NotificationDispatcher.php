<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\Person;
use App\Models\Project;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Services\SettlementEngine;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Turns things that happen in the business into notifications.
 *
 * Controllers call one method here and get on with their day. Every figure is
 * read from the record or from SettlementEngine at the moment of sending, so
 * a message can never quote a number the system does not currently agree with.
 *
 * Like NotificationService, nothing here throws. A financial operation calls
 * these after its own work has committed, and must not care what happens next.
 */
class NotificationDispatcher
{
    public function __construct(private readonly NotificationService $notifications) {}

    /* ===================== Transactions ===================== */

    /**
     * An expense or credit was recorded. Everyone on the project hears about
     * it, because it moves all of their shares - not only the person it was
     * booked against.
     */
    public function transactionCreated(Transaction $transaction): void
    {
        $this->safely(fn () => $this->toProjectPartners(
            $transaction,
            $transaction->type === 'credit' ? 'credit_created' : 'expense_created',
        ));
    }

    /** @param string|null $previousAmount The amount before the edit. */
    public function transactionUpdated(Transaction $transaction, ?string $previousAmount = null): void
    {
        $this->safely(fn () => $this->toProjectPartners(
            $transaction,
            'expense_updated',
            ['previous_amount' => $previousAmount ? Money::format($previousAmount) : '--'],
        ));
    }

    /**
     * Called after the delete has committed, never before - a message about a
     * transaction that then failed to delete would be a lie.
     */
    public function transactionDeleted(Transaction $transaction): void
    {
        $this->safely(fn () => $this->toProjectPartners($transaction, 'expense_deleted'));
    }

    /**
     * @param  array<string, string|null>  $extra
     */
    private function toProjectPartners(Transaction $transaction, string $event, array $extra = []): void
    {
        $project = $transaction->project;

        if (! $project) {
            return;
        }

        $variables = array_merge($this->transactionVariables($transaction), $extra);

        $related = [
            'project_id' => $project->id,
            'transaction_id' => $transaction->id,
        ];

        foreach ($this->partnersOf($project) as $partner) {
            $this->notifications->queue($partner, $event, $variables, $related);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function transactionVariables(Transaction $transaction): array
    {
        $amount = Money::format($transaction->amount);

        return [
            'project_name' => $transaction->project?->name ?? '',
            'company_name' => $transaction->company?->name ?: null,
            'person_name' => $transaction->person?->name ?? '--',
            'expense_title' => $transaction->title,
            'transaction_type' => ucfirst($transaction->type),
            // Both are filled whichever type it is, so a template that names
            // the wrong one still reads correctly.
            'expense_amount' => $amount,
            'credit_amount' => $amount,
            'transaction_date' => $transaction->transaction_date?->format('d M Y'),
        ];
    }

    /* ===================== Settlements ===================== */

    /**
     * A settlement was marked paid. Both sides are told, because both need to
     * agree that the money moved.
     */
    public function settlementPaid(Settlement $settlement): void
    {
        $this->safely(function () use ($settlement) {
            $settlement->loadMissing(['from', 'to', 'project.company']);

            $variables = [
                'project_name' => $settlement->project?->name ?? '',
                'company_name' => $settlement->project?->company?->name ?: null,
                'payer_name' => $settlement->from?->name ?? '--',
                'receiver_name' => $settlement->to?->name ?? '--',
                'settlement_amount' => Money::format($settlement->paid_amount),
                'settlement_status' => $settlement->status_label,
            ];

            $related = [
                'project_id' => $settlement->project_id,
                'settlement_id' => $settlement->id,
            ];

            foreach ([$settlement->from, $settlement->to] as $person) {
                if ($person) {
                    $this->notifications->queue($person, 'settlement_paid', $variables, $related);
                }
            }
        });
    }

    /**
     * Settlement reminders for a whole project.
     *
     * Debtors get a reminder naming who they owe and how much; creditors get a
     * summary naming who owes them. Each message is built for its recipient,
     * so nobody is sent a list of somebody else's obligations.
     *
     * Every amount comes from the engine, which recalculates from the current
     * transactions - there is nothing stored to go stale.
     *
     * @param  bool  $force  A manual "remind everyone" from the admin panel,
     *                       which overrides preference switches.
     * @return int  How many messages were queued.
     */
    public function settlementReminders(Project $project, bool $force = false): int
    {
        return $this->safely(function () use ($project, $force) {
            $plan = SettlementEngine::forProject($project);
            $queued = 0;

            $shared = [
                'project_name' => $project->name,
                'company_name' => $project->company?->name ?: null,
                'equal_share' => $this->money($plan['share']),
                'total_expense' => $this->money($plan['total_spent']),
                'total_credit' => $this->money($plan['total_received']),
                'balance' => $this->money($plan['pool']),
                'pending_settlement' => $this->money($plan['to_settle']),
                'settlement_status' => 'Pending',
            ];

            $related = ['project_id' => $project->id];

            // One reminder per debt, so the message can name the single person
            // to pay rather than a list to interpret.
            foreach ($plan['transfers'] as $transfer) {
                $queued += count($this->notifications->queue(
                    $transfer['from'],
                    'settlement_reminder',
                    $shared + [
                        'amount_to_pay' => $this->money($transfer['amount']),
                        'receiver_name' => $transfer['to']->name,
                        'payer_name' => $transfer['from']->name,
                    ],
                    $related,
                    force: $force,
                ));
            }

            // One summary per creditor, listing everyone who owes them.
            foreach ($this->creditors($plan['transfers']) as $summary) {
                $queued += count($this->notifications->queue(
                    $summary['person'],
                    'settlement_summary',
                    $shared + [
                        'amount_to_receive' => $this->money($summary['total']),
                        'receiver_name' => $summary['person']->name,
                        'payer_breakdown' => $summary['breakdown'],
                    ],
                    $related,
                    force: $force,
                ));
            }

            return $queued;
        }) ?? 0;
    }

    /**
     * Groups the plan's transfers by who is owed, with a readable breakdown.
     *
     * @param  array<int, array{from: Person, to: Person, amount: int}>  $transfers
     * @return array<int, array{person: Person, total: int, breakdown: string}>
     */
    private function creditors(array $transfers): array
    {
        $byReceiver = [];

        foreach ($transfers as $transfer) {
            $id = $transfer['to']->id;

            $byReceiver[$id] ??= ['person' => $transfer['to'], 'total' => 0, 'lines' => []];
            $byReceiver[$id]['total'] += $transfer['amount'];
            $byReceiver[$id]['lines'][] = $transfer['from']->name.' - '.$this->money($transfer['amount']);
        }

        return array_values(array_map(fn (array $row) => [
            'person' => $row['person'],
            'total' => $row['total'],
            'breakdown' => implode("\n", $row['lines']),
        ], $byReceiver));
    }

    /**
     * The monthly position of a project, to everyone on it.
     *
     * @return int  How many messages were queued.
     */
    public function monthlySummary(Project $project, bool $force = false): int
    {
        return $this->safely(function () use ($project, $force) {
            $plan = SettlementEngine::forProject($project);
            $queued = 0;

            $variables = [
                'project_name' => $project->name,
                'company_name' => $project->company?->name ?: null,
                'total_credit' => $this->money($plan['total_received']),
                'total_expense' => $this->money($plan['total_spent']),
                'balance' => $this->money($plan['pool']),
                'equal_share' => $this->money($plan['share']),
                'pending_settlement' => $this->money($plan['to_settle']),
            ];

            foreach ($plan['partners'] as $row) {
                $queued += count($this->notifications->queue(
                    $row['person'],
                    'monthly_summary',
                    $variables + [
                        'amount_to_pay' => $row['position'] < 0 ? $this->money(-$row['position']) : $this->money(0),
                        'amount_to_receive' => $row['position'] > 0 ? $this->money($row['position']) : $this->money(0),
                    ],
                    ['project_id' => $project->id],
                    force: $force,
                ));
            }

            return $queued;
        }) ?? 0;
    }

    /**
     * A one-off send an admin asked for, on one channel, about one settlement.
     *
     * @return NotificationLog[]
     */
    public function manualSettlement(Settlement $settlement, string $channel): array
    {
        return $this->safely(function () use ($settlement, $channel) {
            $settlement->loadMissing(['from', 'to', 'project.company']);

            if (! $settlement->from) {
                return [];
            }

            $plan = $settlement->project
                ? SettlementEngine::forProject($settlement->project)
                : null;

            return $this->notifications->queue(
                $settlement->from,
                'settlement_reminder',
                [
                    'project_name' => $settlement->project?->name ?? '',
                    'company_name' => $settlement->project?->company?->name ?: null,
                    'amount_to_pay' => Money::format($settlement->outstanding),
                    'receiver_name' => $settlement->to?->name ?? '--',
                    'payer_name' => $settlement->from->name,
                    'settlement_status' => $settlement->status_label,
                    'equal_share' => $plan ? $this->money($plan['share']) : null,
                ],
                [
                    'project_id' => $settlement->project_id,
                    'settlement_id' => $settlement->id,
                ],
                [$channel],
                force: true,
            );
        }) ?? [];
    }

    /* ===================== Helpers ===================== */

    /** Everyone assigned to a project, as notification recipients. */
    private function partnersOf(Project $project)
    {
        return $project->people()->where('people.status', true)->get();
    }

    private function money(int $paise): string
    {
        return Money::format(SettlementEngine::rupees($paise));
    }

    /**
     * Runs a block and swallows anything it throws.
     *
     * This is the line the spec draws: an expense that saved successfully must
     * stay saved even if every notification path is broken. The failure is
     * recorded where an operator will find it, and the caller never knows.
     */
    private function safely(callable $work): mixed
    {
        try {
            return $work();
        } catch (\Throwable $e) {
            Log::error('Notification dispatch failed: '.$e->getMessage(), [
                'exception' => $e::class,
            ]);

            return null;
        }
    }
}
