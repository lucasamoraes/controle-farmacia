<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'company_id',
        'purchase_list_id',
        'created_by',
        'status',
        'quoted_at',
        'finalized_at',
    ];

    protected $casts = [
        'quoted_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseList(): BelongsTo
    {
        return $this->belongsTo(PurchaseList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'quotation_suppliers')->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(QuotationPrice::class);
    }
}
