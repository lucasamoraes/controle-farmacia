<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMovementType extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'kind',
        'requires_worked_date',
        'allows_paid_outside',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'requires_worked_date' => 'boolean',
        'allows_paid_outside' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
