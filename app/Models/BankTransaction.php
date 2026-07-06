<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'company_id',
        'bank_import_id',
        'matched_payable_id',
        'transaction_date',
        'description',
        'amount',
        'type',
        'reconciled_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankImport::class, 'bank_import_id');
    }

    public function matchedPayable(): BelongsTo
    {
        return $this->belongsTo(Payable::class, 'matched_payable_id');
    }
}
