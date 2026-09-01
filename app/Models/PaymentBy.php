<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentBy extends Model
{
    use SoftDeletes, LogsActivity;

    // Eloquent would pluralise this to "payment_bies".
    protected $table = 'payment_bys';

    protected $fillable = ['name', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
