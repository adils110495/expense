<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One notification attempt and what became of it.
 *
 * Written before the send is attempted, so a message that crashes the worker
 * still leaves a trace. The provider response then moves it on to sent,
 * delivered, read or failed.
 */
class NotificationLog extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
        'bounced' => 'Bounced',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that mean the provider accepted it and nothing went wrong. */
    public const SUCCESSFUL = ['sent', 'delivered', 'read'];

    protected $fillable = [
        'person_id', 'recipient_name', 'recipient', 'channel', 'event',
        'subject', 'body', 'project_id', 'transaction_id', 'settlement_id',
        'provider', 'provider_message_id', 'status', 'error', 'attempts',
        'sent_at', 'delivered_at', 'read_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /* ===================== Filters ===================== */

    public function scopeOfChannel(Builder $query, ?string $channel): Builder
    {
        return $channel ? $query->where('channel', $channel) : $query;
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeOfEvent(Builder $query, ?string $event): Builder
    {
        return $event ? $query->where('event', $event) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('recipient_name', 'like', $like)
            ->orWhere('recipient', 'like', $like)
            ->orWhere('subject', 'like', $like));
    }
}
