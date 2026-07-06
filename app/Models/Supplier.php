<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'financial_category_id',
        'name',
        'trade_name',
        'document',
        'legal_status',
        'email',
        'phone',
        'street',
        'number',
        'district',
        'city',
        'state',
        'zip_code',
        'main_activity',
        'cnpj_checked_at',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cnpj_checked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }
}
