<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'company_id',
        'employee_code',
        'name',
        'document',
        'role',
        'cbo_code',
        'department',
        'branch',
        'salary',
        'fixed_salary',
        'variable_salary',
        'base_salary',
        'inss_salary',
        'fgts_base',
        'fgts_month',
        'irrf_base',
        'irrf_bracket',
        'payment_day',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'fixed_salary' => 'decimal:2',
        'variable_salary' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'inss_salary' => 'decimal:2',
        'fgts_base' => 'decimal:2',
        'fgts_month' => 'decimal:2',
        'irrf_base' => 'decimal:2',
        'irrf_bracket' => 'decimal:2',
        'payment_day' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(EmployeePayrollItem::class);
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class);
    }
}
