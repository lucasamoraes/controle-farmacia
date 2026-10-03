<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingImport extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'original_filename',
        'rows_created',
        'rows_updated',
        'rows_skipped',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(MarketingCampaignMetric::class);
    }
}
