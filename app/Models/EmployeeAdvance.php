<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'advance_date',
        'deduct_month',
        'description',
        'amount',
        'payment_method',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'deduct_month' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
