<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Project;
use App\Models\Settlement;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Equal partner distribution and settlement.
 *
 * The rule: however the money actually moved, every partner on a project ends
 * up with the same net position. Whoever is holding more than their share
 * pays; whoever is holding less receives.
 *
 * Costs and income are kept apart. Each is split equally in its own right -
 * an expense ledger for money laid out, an income ledger for money drawn -
 * and the net position is their sum, partner by partner. Only the net is
 * meant to be paid; the two sides exist so a loss on the costs is not hidden
 * behind income, or the other way round.
 *
 * Nothing here is stored. The plan is derived from the transactions each time
 * it is asked for, so adding, editing or deleting an expense or credit, or
 * adding or removing a partner, changes the answer immediately and no stale
 * total can survive.
 *
 * All arithmetic is in integer paise. Money never touches a float, and the
 * per-partner shares are built so they sum to the pool exactly - which is
 * what makes total-payable equal total-receivable to the paisa, with no
 * rounding fudge needed on any individual transfer.
 */
class SettlementEngine
{
    /* ===================== Entry points ===================== */

    /**
     * The settlement plan for one project, optionally for a period only.
     *
     * @return array<string, mixed>
     */
    public static function forProject(Project $project, ?string $from = null, ?string $to = null): array
    {
        return self::forProjects([$project], $from, $to)[$project->id];
    }

    /**
     * Plans for many projects at a fixed cost of four queries in total, so a
     * dashboard covering every project is no more expensive than one project.
     *
     * A date range narrows both sides of the calculation: the transactions
     * that create the debt, and the payments that have already settled part of
     * it. Narrowing only one would leave a period showing a month's expenses
     * against every settlement ever made.
     *
     * @param  iterable<Project>  $projects
     * @return array<int, array<string, mixed>>  keyed by project id
     */
    public static function forProjects(iterable $projects, ?string $from = null, ?string $to = null): array
    {
        $projects = collect($projects)->keyBy('id');
        $projectIds = $projects->keys()->all();

        if (! $projectIds) {
            return [];
        }

        $ledger = self::ledger($projectIds, $from, $to);
        $settled = self::settled($projectIds, $from, $to);
        $partners = self::partners($projectIds, $ledger, $settled);

        $plans = [];

        foreach ($projects as $id => $project) {
            $plans[$id] = self::compute(
                $project,
                $partners[$id] ?? collect(),
                $ledger[$id] ?? [],
                $settled[$id] ?? [],
            );
        }

        return $plans;
    }

    /* ===================== Data gathering ===================== */

    /**
     * Spent and received per person per project, in paise.
     *
     * "Spent" is money a partner paid out as an expense; "received" is money
     * a partner took from the project. They are deliberately kept apart - the
     * spec is explicit that they are not the same transaction type.
     *
     * @param  int[]  $projectIds
     * @return array<int, array<int, array{spent: int, received: int}>>
     */
    private static function ledger(array $projectIds, ?string $from = null, ?string $to = null): array
    {
        $rows = Transaction::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('person_id')
            ->between($from, $to)
            ->toBase()
            ->select('project_id', 'person_id', 'type', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id', 'person_id', 'type')
            ->get();

        $ledger = [];

        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;
            $personId = (int) $row->person_id;

            $ledger[$projectId][$personId] ??= ['spent' => 0, 'received' => 0];

            // An expense is money the partner laid out; a credit is money the
            // partner took in.
            $key = $row->type === 'expense' ? 'spent' : 'received';
            $ledger[$projectId][$personId][$key] += self::paise((string) $row->total);
        }

        return $ledger;
    }

    /**
     * Money already moved between partners, in paise: what each has paid out
     * and taken in through recorded settlements.
     *
     * paid_amount is what counts, not the status label, so a partially paid
     * transfer discharges exactly the part that was actually paid.
     *
     * Grouped by the side each payment clears. A payment recorded against the
     * expense list has to come off the expense position, or the row it settled
     * stays on that list for ever - paying it would change only the net.
     *
     * @param  int[]  $projectIds
     * @return array<int, array<int, array<string, array{in: int, out: int}>>>
     */
    private static function settled(array $projectIds, ?string $from = null, ?string $to = null): array
    {
        // A settlement's effective date is the day it was settled, falling
        // back to the day it was recorded. Without the fallback a paid row
        // with no date entered would drop out of every period and its money
        // would look like it had never moved.
        $effectiveDate = 'COALESCE(settled_on, DATE(created_at))';

        $rows = Settlement::query()
            ->whereIn('project_id', $projectIds)
            ->where('paid_amount', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->when($from, fn ($q) => $q->whereRaw("{$effectiveDate} >= ?", [$from]))
            ->when($to, fn ($q) => $q->whereRaw("{$effectiveDate} <= ?", [$to]))
            ->toBase()
            ->select('project_id', 'from_person_id', 'to_person_id', 'kind', 'paid_amount')
            ->get();

        $settled = [];

        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;
            $amount = self::paise((string) $row->paid_amount);
            $kind = in_array($row->kind, ['expense', 'credit'], true) ? $row->kind : 'net';

            $payer = (int) $row->from_person_id;
            $payee = (int) $row->to_person_id;

            $settled[$projectId][$payer] ??= self::noSettlements();
            $settled[$projectId][$payee] ??= self::noSettlements();

            $settled[$projectId][$payer][$kind]['out'] += $amount;
            $settled[$projectId][$payee][$kind]['in'] += $amount;
        }

        return $settled;
    }

    /** An empty settled-so-far record, one bucket per side. */
    private static function noSettlements(): array
    {
        return [
            'expense' => ['in' => 0, 'out' => 0],
            'credit' => ['in' => 0, 'out' => 0],
            'net' => ['in' => 0, 'out' => 0],
        ];
    }

    /**
     * The partners of each project: everyone assigned to it, plus anyone who
     * holds money on it or has settled money on it.
     *
     * The last two matter for correctness, not tidiness. Every person id that
     * appears in either map has to be a partner, or their side of the sum is
     * silently dropped while the other side is kept, and the positions stop
     * adding up to zero. That is easy to hit once a period filter is applied:
     * someone can have settled a payment inside the period without having any
     * transaction of their own in it.
     *
     * @param  int[]  $projectIds
     * @param  array<int, array<int, array{spent: int, received: int}>>  $ledger
     * @param  array<int, array<int, array{in: int, out: int}>>  $settled
     * @return array<int, Collection<int, Person>>
     */
    private static function partners(array $projectIds, array $ledger, array $settled = []): array
    {
        $assignments = DB::table('project_person')
            ->whereIn('project_id', $projectIds)
            ->get(['project_id', 'person_id']);

        $wanted = [];

        foreach ($assignments as $row) {
            $wanted[(int) $row->project_id][] = (int) $row->person_id;
        }

        foreach ([$ledger, $settled] as $map) {
            foreach ($map as $projectId => $people) {
                foreach (array_keys($people) as $personId) {
                    $wanted[$projectId][] = $personId;
                }
            }
        }

        $ids = collect($wanted)->flatten()->unique()->all();

        if (! $ids) {
            return [];
        }

        // withTrashed: a deleted partner's money still has to be accounted for.
        $people = Person::withTrashed()->whereIn('id', $ids)->orderBy('name')->get()->keyBy('id');

        $partners = [];

        foreach ($wanted as $projectId => $personIds) {
            $partners[$projectId] = collect($personIds)
                ->unique()
                ->map(fn (int $personId) => $people->get($personId))
                ->filter()
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        }

        return $partners;
    }

    /* ===================== The calculation ===================== */

    /**
     * Two independent ledgers, then their sum.
     *
     * Expenses and income are settled separately because they answer different
     * questions. Costs are a burden to share: whoever laid out more than their
     * share of the spending gets reimbursed. Income is a benefit to share:
     * whoever drew more than their share of it pays the excess back. Netting
     * the two into one figure hides both, which is why each is computed and
     * shown in its own right.
     *
     * The combined position is the sum of the two, so the three tables always
     * reconcile: expense position + profit position = net position, partner by
     * partner. Only the net list is meant to be paid - settling the two
     * separately would move money twice between the same pair.
     *
     * @param  Collection<int, Person>  $partners
     * @param  array<int, array{spent: int, received: int}>  $ledger
     * @param  array<int, array{in: int, out: int}>  $settled
     * @return array<string, mixed>
     */
    private static function compute(Project $project, Collection $partners, array $ledger, array $settled): array
    {
        $count = $partners->count();

        $totalSpent = 0;
        $totalReceived = 0;

        foreach ($ledger as $entry) {
            $totalSpent += $entry['spent'];
            $totalReceived += $entry['received'];
        }

        $pool = $totalReceived - $totalSpent;

        if ($count === 0) {
            return self::empty($project, $totalSpent, $totalReceived, $pool);
        }

        // Each side is split so its own shares sum to its own total exactly.
        // Doing it per side rather than on the net is what lets the two
        // ledgers each balance to zero on their own.
        $expenseShares = self::split($totalSpent, $count);
        $incomeShares = self::split($totalReceived, $count);

        $rows = [];
        $index = 0;

        foreach ($partners as $person) {
            $entry = $ledger[$person->id] ?? ['spent' => 0, 'received' => 0];
            $moved = $settled[$person->id] ?? self::noSettlements();

            // Each side is reduced by the payments made against that side, so
            // settling an expense clears it from the expense list rather than
            // only moving the net.
            //
            // Paid more of the costs than their share -> positive -> owed it back.
            $expensePosition = $entry['spent'] - $expenseShares[$index]
                + $moved['expense']['out'] - $moved['expense']['in'];

            // Drew more of the income than their share -> negative -> owes it back.
            $incomePosition = $incomeShares[$index] - $entry['received']
                + $moved['credit']['out'] - $moved['credit']['in'];

            // A payment recorded against the net belongs to neither side, so
            // it lands here and here only.
            $position = $expensePosition + $incomePosition
                + $moved['net']['out'] - $moved['net']['in'];

            $rows[$person->id] = [
                'person' => $person,
                'spent' => $entry['spent'],
                'received' => $entry['received'],
                // Totalled across all three sides for the summary column.
                'settled_in' => array_sum(array_column($moved, 'in')),
                'settled_out' => array_sum(array_column($moved, 'out')),

                'expense_share' => $expenseShares[$index],
                'expense_position' => $expensePosition,

                'income_share' => $incomeShares[$index],
                'income_position' => $incomePosition,

                // The net of the two, which is what actually gets paid.
                'share' => $incomeShares[$index] - $expenseShares[$index],
                'position' => $position,

                'pays' => [],
                'receives' => [],
            ];

            $index++;
        }

        $transfers = self::match($rows, 'position');
        $expenseTransfers = self::match($rows, 'expense_position');
        $incomeTransfers = self::match($rows, 'income_position');

        // Annotate each partner with their side of every net transfer, so the
        // table can say who they pay rather than only how much.
        foreach ($transfers as $transfer) {
            $rows[$transfer['from']->id]['pays'][] = $transfer;
            $rows[$transfer['to']->id]['receives'][] = $transfer;
        }

        return [
            'project' => $project,
            'partner_count' => $count,
            'total_spent' => $totalSpent,
            'total_received' => $totalReceived,
            'pool' => $pool,

            // Headline shares. Individual shares can differ by a paisa where a
            // total does not divide evenly.
            'share' => intdiv($pool, $count),
            'expense_share' => intdiv($totalSpent, $count),
            'income_share' => intdiv($totalReceived, $count),

            'partners' => array_values($rows),
            'by_person' => $rows,

            // What to actually pay.
            'transfers' => $transfers,
            'to_settle' => self::sum($transfers),

            // The same money, shown as the two questions it answers.
            'expense_transfers' => $expenseTransfers,
            'income_transfers' => $incomeTransfers,
            'expense_to_settle' => self::sum($expenseTransfers),
            'income_to_settle' => self::sum($incomeTransfers),

            'is_settled' => self::sum($transfers) === 0,
        ];
    }

    /** @param array<int, array{amount: int}> $transfers */
    private static function sum(array $transfers): int
    {
        $total = 0;

        foreach ($transfers as $transfer) {
            $total += $transfer['amount'];
        }

        return $total;
    }

    /** The shape returned for a project with nobody on it. */
    private static function empty(Project $project, int $spent, int $received, int $pool): array
    {
        return [
            'project' => $project,
            'partner_count' => 0,
            'total_spent' => $spent,
            'total_received' => $received,
            'pool' => $pool,
            'share' => 0,
            'expense_share' => 0,
            'income_share' => 0,
            'partners' => [],
            'by_person' => [],
            'transfers' => [],
            'to_settle' => 0,
            'expense_transfers' => [],
            'income_transfers' => [],
            'expense_to_settle' => 0,
            'income_to_settle' => 0,
            'is_settled' => true,
        ];
    }


    /**
     * Splits an amount into $count parts that sum back to it exactly.
     *
     * The remainder is spread one paisa at a time rather than dumped on one
     * partner, and it works for a negative pool too (a project that has spent
     * more than it took in), where the remainder is negative.
     *
     * @return int[]
     */
    private static function split(int $amount, int $count): array
    {
        $base = intdiv($amount, $count);
        $remainder = $amount - ($base * $count);
        $step = $remainder < 0 ? -1 : 1;
        $spread = abs($remainder);

        $shares = [];

        for ($i = 0; $i < $count; $i++) {
            $shares[] = $base + ($i < $spread ? $step : 0);
        }

        return $shares;
    }

    /**
     * Turns the positions into an efficient list of who pays whom.
     *
     * Largest debtor against largest creditor, repeatedly. Because the
     * positions sum to zero and are whole paise, this always clears exactly,
     * and it needs at most one transfer fewer than there are partners.
     *
     * Finding the true minimum number of transfers is NP-hard (it is a
     * subset-sum problem), so this is the standard greedy answer: it never
     * routes money through a third partner the way a naive per-expense split
     * would, which is what the requirement is actually about.
     *
     * The same routine serves all three ledgers - net, expenses alone and
     * income alone - because each of their position columns sums to zero on
     * its own. $key picks which one to settle.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string  $key  'position', 'expense_position' or 'income_position'
     * @return array<int, array{from: Person, to: Person, amount: int}>
     */
    private static function match(array $rows, string $key): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($rows as $row) {
            if ($row[$key] < 0) {
                $debtors[] = ['person' => $row['person'], 'amount' => -$row[$key]];
            } elseif ($row[$key] > 0) {
                $creditors[] = ['person' => $row['person'], 'amount' => $row[$key]];
            }
        }

        // Biggest first on both sides: settling the largest debt against the
        // largest credit clears whole partners soonest, which is what keeps
        // the number of transfers down.
        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $transfers = [];
        $d = 0;
        $c = 0;

        while ($d < count($debtors) && $c < count($creditors)) {
            $amount = min($debtors[$d]['amount'], $creditors[$c]['amount']);

            if ($amount > 0) {
                $transfers[] = [
                    'from' => $debtors[$d]['person'],
                    'to' => $creditors[$c]['person'],
                    'amount' => $amount,
                ];
            }

            $debtors[$d]['amount'] -= $amount;
            $creditors[$c]['amount'] -= $amount;

            if ($debtors[$d]['amount'] === 0) {
                $d++;
            }

            if ($creditors[$c]['amount'] === 0) {
                $c++;
            }
        }

        return $transfers;
    }

    /* ===================== Money helpers ===================== */

    /** A decimal string of rupees to whole paise, with no float in between. */
    public static function paise(string $amount): int
    {
        return (int) bcmul($amount === '' ? '0' : $amount, '100', 0);
    }

    /** Paise back to the decimal string the rest of the app formats. */
    public static function rupees(int $paise): string
    {
        return bcdiv((string) $paise, '100', 2);
    }
}
