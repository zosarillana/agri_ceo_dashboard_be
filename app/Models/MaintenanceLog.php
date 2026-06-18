<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    use Auditable;
    protected $fillable = [
        'maintenance_unit_id',
        'checked_by',
        'status',
        'notes',
        'checked_at',
        'next_scheduled_at',
        'duration_minutes',
    ];

    protected $casts = [
        'checked_at'         => 'datetime',
        'next_scheduled_at'  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The unit this log entry belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(MaintenanceUnit::class, 'maintenance_unit_id');
    }

    /**
     * The user who performed this check.
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Logs submitted today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('checked_at', today());
    }

    /**
     * Logs within a date range.
     */
    public function scopeBetween($query, string $from, string $to)
    {
        return $query->whereBetween('checked_at', [$from, $to]);
    }

    /**
     * Logs for a specific status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Logs submitted by a specific user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('checked_by', $userId);
    }
}