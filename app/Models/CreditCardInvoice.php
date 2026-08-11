<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'payable_id',
        'credit_card_id',
        'card_name',
        'reference_month',
        'due_date',
        'total_amount',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'reference_month' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditCardInvoiceItem::class);
    }
}
