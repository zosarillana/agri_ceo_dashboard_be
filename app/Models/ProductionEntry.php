<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionEntry extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'product_id',
        'production_date',
        'actual_output',
        'target_output',
        'remarks',
    ];

    protected $casts = [
        'production_date' => 'date',
        'actual_output' => 'decimal:2',
        'target_output' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
