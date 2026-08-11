<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePosition extends Model
{
    protected $fillable = ['company_id', 'name', 'cbo_code', 'additional_type', 'additional_percent', 'is_active'];

    protected $casts = ['additional_percent' => 'decimal:2', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
