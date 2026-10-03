<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignMetric extends Model
{
    protected $fillable = [
        'company_id',
        'marketing_import_id',
        'fingerprint',
        'report_start',
        'report_end',
        'campaign_name',
        'delivery_status',
        'attribution_setting',
        'results',
        'result_indicator',
        'reach',
        'impressions',
        'link_clicks',
        'all_clicks',
        'landing_page_views',
        'frequency',
        'amount_spent',
        'cpc',
        'cpm',
        'ctr',
        'cost_per_result',
        'raw_data',
    ];

    protected $casts = [
        'report_start' => 'date',
        'report_end' => 'date',
        'results' => 'decimal:4',
        'amount_spent' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(MarketingImport::class, 'marketing_import_id');
    }
}
