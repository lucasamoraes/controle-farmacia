<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationSupplier extends Model
{
    protected $fillable = [
        'quotation_id',
        'supplier_id',
        'display_name',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(QuotationPrice::class);
    }

    public function getQuotedNameAttribute(): string
    {
        return $this->display_name ?: ($this->supplier?->name ?? 'Fornecedor');
    }
}
