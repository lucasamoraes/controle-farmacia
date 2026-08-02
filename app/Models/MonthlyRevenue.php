<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyRevenue extends Model
{
    protected $fillable = [
        'company_id',
        'reference_month',
        'gross_revenue',
        'revenue_to_receive',
        'cost_of_goods_sold',
        'cmv_percentage',
        'sales_count',
        'delivery_sales_count',
        'delivery_revenue',
        'counter_sales_count',
        'counter_revenue',
        'average_ticket',
        'items_per_ticket',
        'notes',
        'important_info',
    ];

    protected $casts = [
        'reference_month' => 'date',
        'gross_revenue' => 'decimal:2',
        'revenue_to_receive' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
        'cmv_percentage' => 'decimal:2',
        'delivery_revenue' => 'decimal:2',
        'counter_revenue' => 'decimal:2',
        'average_ticket' => 'decimal:2',
        'items_per_ticket' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
