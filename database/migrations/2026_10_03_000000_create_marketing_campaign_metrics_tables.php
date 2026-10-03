<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_import_id')->nullable()->constrained('marketing_imports')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->date('report_start');
            $table->date('report_end');
            $table->string('campaign_name');
            $table->string('delivery_status')->nullable();
            $table->string('attribution_setting')->nullable();
            $table->decimal('results', 14, 4)->default(0);
            $table->string('result_indicator')->nullable();
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('link_clicks')->default(0);
            $table->unsignedBigInteger('all_clicks')->default(0);
            $table->unsignedBigInteger('landing_page_views')->default(0);
            $table->decimal('frequency', 12, 6)->default(0);
            $table->decimal('amount_spent', 14, 2)->default(0);
            $table->decimal('cpc', 14, 6)->default(0);
            $table->decimal('cpm', 14, 6)->default(0);
            $table->decimal('ctr', 14, 6)->default(0);
            $table->decimal('cost_per_result', 14, 6)->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fingerprint'], 'marketing_metric_company_fingerprint_unique');
            $table->index(['company_id', 'report_start'], 'marketing_metric_company_period_index');
            $table->index(['company_id', 'campaign_name'], 'marketing_metric_company_campaign_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_metrics');
        Schema::dropIfExists('marketing_imports');
    }
};
