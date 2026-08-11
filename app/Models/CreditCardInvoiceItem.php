<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInvoiceItem extends Model
{
    protected $fillable = [
        'credit_card_invoice_id',
        'financial_category_id',
        'description',
        'amount',
        'is_recurring',
        'recurrence_start_month',
        'recurrence_end_month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_start_month' => 'date',
        'recurrence_end_month' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'credit_card_invoice_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }
}
