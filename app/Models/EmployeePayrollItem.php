<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollItem extends Model
{
    protected $fillable = [
        'employee_id',
        'reference_month',
        'event_type',
        'worked_date',
        'paid_outside',
        'paid_at',
        'code',
        'description',
        'reference',
        'earning',
        'deduction',
    ];

    protected $casts = [
        'reference_month' => 'date',
        'worked_date' => 'date',
        'paid_outside' => 'boolean',
        'paid_at' => 'date',
        'earning' => 'decimal:2',
        'deduction' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
