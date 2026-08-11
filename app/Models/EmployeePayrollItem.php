<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollItem extends Model
{
    protected $fillable = [
        'employee_id',
        'reference_month',
        'code',
        'description',
        'reference',
        'earning',
        'deduction',
    ];

    protected $casts = [
        'reference_month' => 'date',
        'earning' => 'decimal:2',
        'deduction' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
