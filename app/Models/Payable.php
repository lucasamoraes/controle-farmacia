<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payable extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'financial_category_id',
        'description',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'source',
        'document_number',
        'barcode',
        'digitable_line',
        'attachment_path',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function boletoUpload(): HasOne
    {
        return $this->hasOne(BoletoUpload::class);
    }
}
