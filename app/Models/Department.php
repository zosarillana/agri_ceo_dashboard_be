<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
    ];

    /**
     * Users belonging to this department
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}