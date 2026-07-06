<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoletoUpload extends Model
{
    protected $fillable = [
        'company_id',
        'payable_id',
        'original_file_name',
        'stored_path',
        'extracted_text',
        'parsed_data',
        'processing_status',
        'error_message',
    ];

    protected $casts = [
        'parsed_data' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
