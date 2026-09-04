<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\CompanyScoped;
use App\Support\CompanyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model implements CompanyScoped
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const TYPES = ['expense', 'credit'];

    /** An unfiled row - company_id still null - is an admin's to deal with. */
    public function accessibleToCurrentActor(): bool
    {
        return CompanyAccess::allows($this->company_id);
    }

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'other' => 'Other',
    ];

    protected $fillable = [
        'type', 'company_id', 'project_id', 'person_id',
        'title', 'description', 'category_id', 'amount',
        'transaction_date', 'payment_method', 'payment_by_id', 'location', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            // decimal:2 keeps the value a string, so no float math sneaks in.
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function paymentBy(): BelongsTo
    {
        return $this->belongsTo(PaymentBy::class, 'payment_by_id');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    /**
     * Narrows to the companies the signed-in actor may see.
     *
     * whereIn drops rows whose company_id is NULL, and that is deliberate: an
     * unfiled transaction belongs to no company, so nobody working under a
     * company restriction has any business seeing it. An admin, who passes
     * null here, still sees them - and the bulk assign screen is theirs.
     *
     * @param  int[]|null  $companyIds
     */
    public function scopeForCompanies(Builder $query, ?array $companyIds): Builder
    {
        return $companyIds === null ? $query : $query->whereIn('transactions.company_id', $companyIds);
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('transaction_date', '<=', $to));
    }

    /**
     * Free-text search across the fields the spec lists: title, description,
     * notes and the related category name, plus the hierarchy the record
     * hangs off - searching a company or a person's name finds their money.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('project', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('person', fn (Builder $c) => $c->where('name', 'like', $like));
        });
    }

    /**
     * Narrows to one branch of the hierarchy. Each level is optional and they
     * compose: company alone gives the whole company, company + project one
     * project, all three one person's activity on one project.
     */
    public function scopeInHierarchy(
        Builder $query,
        mixed $companyId = null,
        mixed $projectId = null,
        mixed $personId = null,
    ): Builder {
        return $query
            ->when(filled($companyId), fn (Builder $q) => $q->where('company_id', $companyId))
            ->when(filled($projectId), fn (Builder $q) => $q->where('project_id', $projectId))
            ->when(filled($personId), fn (Builder $q) => $q->where('person_id', $personId));
    }

    /**
     * Rows whose Company -> Project -> Person chain is incomplete.
     *
     * Any one missing level is enough: a transaction with a company but no
     * project never reaches a project or person total, and takes no part in
     * that project's settlement. These are exactly the rows the bulk assign
     * screen exists to clear.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('company_id')
            ->orWhereNull('project_id')
            ->orWhereNull('person_id'));
    }

    /** True once the record sits on a complete Company -> Project -> Person path. */
    public function getIsAssignedAttribute(): bool
    {
        return $this->company_id !== null
            && $this->project_id !== null
            && $this->person_id !== null;
    }
}
