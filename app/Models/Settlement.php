<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\CompanyScoped;
use App\Support\CompanyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One recorded payment from one partner to another, on one project.
 *
 * The settlement *plan* is never stored - SettlementEngine derives it from
 * the transactions every time it is asked. A row here is the record of money
 * that actually moved, which the engine subtracts so the same debt is not
 * suggested twice.
 */
class Settlement extends Model implements CompanyScoped
{
    use SoftDeletes, LogsActivity;

    /** A settlement inherits its company from the project it settles. */
    public function accessibleToCurrentActor(): bool
    {
        return CompanyAccess::allows($this->project?->company_id);
    }

    public const STATUSES = [
        'pending' => 'Pending',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Which side of the books a payment clears. Expenses and income settle as
     * two separate lists, so a pair of partners can owe on each at once.
     */
    public const KINDS = [
        'expense' => 'Expense share',
        'credit' => 'Credit / profit share',
        'net' => 'Net of both',
    ];

    /** The same list expenses and credits use, so the two read alike. */
    public const PAYMENT_METHODS = Transaction::PAYMENT_METHODS;

    protected $fillable = [
        'project_id', 'from_person_id', 'to_person_id', 'kind',
        'amount', 'paid_amount', 'status', 'payment_method',
        'settled_on', 'location', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'from_person_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'to_person_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /** Receipts for the payment, on the same private-disk pipeline as expenses. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** What is still owed on this record. */
    public function getOutstandingAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return '0.00';
        }

        return bcsub((string) $this->amount, (string) $this->paid_amount, 2);
    }

    /**
     * Rows that still represent an intention to pay - the ones worth showing
     * as "recorded, awaiting payment" beside the live plan.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'partially_paid']);
    }

    public function scopeOfProject(Builder $query, mixed $projectId): Builder
    {
        return $projectId ? $query->where('project_id', $projectId) : $query;
    }

    /**
     * Narrows to the companies the signed-in actor may see. A settlement has
     * no company of its own; it inherits the one on its project.
     *
     * @param  int[]|null  $companyIds  Null for an admin: no restriction.
     */
    public function scopeForCompanies(Builder $query, ?array $companyIds): Builder
    {
        if ($companyIds === null) {
            return $query;
        }

        return $query->whereHas('project', fn (Builder $p) => $p->whereIn('projects.company_id', $companyIds));
    }
}
