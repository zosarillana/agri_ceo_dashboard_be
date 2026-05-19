<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceUnit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'plant_id',
        'parent_id',
        'name',
        'status',
        'notes',
        'last_checked_at',
        'next_scheduled_at',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'last_checked_at'    => 'datetime',
        'next_scheduled_at'  => 'datetime',
        'is_active'          => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Parent unit — null if this is a top-level unit.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MaintenanceUnit::class, 'parent_id');
    }

    /**
     * Direct children (sub-units) of this unit.
     * e.g. Liquid Line → [Kumar Expeller, CWC, FPCC]
     */
    public function children(): HasMany
    {
        return $this->hasMany(MaintenanceUnit::class, 'parent_id')
                    ->orderBy('sort_order');
    }

    /**
     * Recursively eager-load all nested children.
     * Call as: MaintenanceUnit::with('allChildren')
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * All log entries for this unit — full history.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'maintenance_unit_id')
                    ->orderByDesc('checked_at');
    }

    /**
     * The single most recent log entry.
     * Use for dashboard: "last checked by X at Y"
     */
    public function latestLog(): HasOne
    {
        return $this->hasOne(MaintenanceLog::class, 'maintenance_unit_id')
                    ->latestOfMany('checked_at');
    }

    /**
     * Today's log entries only.
     */
    public function todaysLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class, 'maintenance_unit_id')
                    ->whereDate('checked_at', today());
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Units that have NOT been checked today.
     */
    public function scopeUncheckedToday($query)
    {
        return $query->whereDoesntHave('logs', function ($q) {
            $q->whereDate('checked_at', today());
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isSubUnit(): bool
    {
        return $this->parent_id !== null;
    }

    public function isOperational(): bool
    {
        return $this->status === 'operational';
    }

    public function wasCheckedToday(): bool
    {
        return $this->todaysLogs()->exists();
    }
}