<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['expense', 'credit'];

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'other' => 'Other',
    ];

    protected $fillable = [
        'type', 'title', 'description', 'category_id', 'amount',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TransactionAttachment::class);
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

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('transaction_date', '<=', $to));
    }

    /**
     * Free-text search across the fields the spec lists: title, description,
     * notes and the related category name.
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
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like));
        });
    }
}
