<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionEntry extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'product_id',
        'production_date',
        'actual_output',
        'target_output',
        'dly_target',
        'dly_yield',
        'mtd_target',
        'mtd_yield',
        'remarks',
    ];

    protected $casts = [
        'production_date' => 'date',
        'actual_output' => 'decimal:2',
        'target_output' => 'decimal:2',
        'dly_target' => 'decimal:2',
        'dly_yield' => 'decimal:2',
        'mtd_target' => 'decimal:2',
        'mtd_yield' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
