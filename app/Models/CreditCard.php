<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'closing_day',
        'due_day',
        'is_active',
    ];

    protected $casts = [
        'closing_day' => 'integer',
        'due_day' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CreditCardInvoice::class);
    }
}
