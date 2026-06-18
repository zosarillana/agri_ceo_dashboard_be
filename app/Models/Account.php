<?php

// app/Models/Account.php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use Auditable;
    protected $fillable = [
        'description',
        'type',
        'amount',
        'due_date',
        'notes',
        'status',  // Changed from 'is_paid' to 'status'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'due_date' => 'date',
        'status' => 'string',  // Changed from 'is_paid' => 'boolean'
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Updated scopes to work with status
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'delayed', 'pending']);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['paid', 'received']);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDueBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to));
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
    }

    // Helper methods
    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'received']);
    }

    public function isUnpaid(): bool
    {
        return in_array($this->status, ['unpaid', 'delayed', 'pending']);
    }
}