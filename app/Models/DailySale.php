<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySale extends Model
{
    protected $fillable = [
        'company_id',
        'sale_date',
        'weekday',
        'amount',
        'delivery_sales_count',
        'delivery_revenue',
        'counter_sales_count',
        'counter_revenue',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'amount' => 'decimal:2',
        'delivery_revenue' => 'decimal:2',
        'counter_revenue' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
