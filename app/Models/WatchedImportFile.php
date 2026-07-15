<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchedImportFile extends Model
{
    protected $fillable = [
        'path',
        'label',
        'last_modified_at',
        'last_synced_at',
        'last_status',
        'last_error',
        'last_summary',
        'is_active',
    ];

    protected $casts = [
        'last_modified_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_summary' => 'array',
        'is_active' => 'boolean',
    ];
}