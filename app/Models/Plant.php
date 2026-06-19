<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plant extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * All maintenance units that belong to this plant (top-level only).
     * Sub-units are accessed through each unit's ->children relationship.
     */
    public function units(): HasMany
    {
        return $this->hasMany(MaintenanceUnit::class)
                    ->whereNull('parent_id')
                    ->orderBy('sort_order');
    }

    /**
     * All maintenance units (flat — includes sub-units).
     */
    public function allUnits(): HasMany
    {
        return $this->hasMany(MaintenanceUnit::class)->orderBy('sort_order');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}