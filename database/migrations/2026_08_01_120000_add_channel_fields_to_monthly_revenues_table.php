<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_revenues', function (Blueprint $table) {
            $table->unsignedInteger('delivery_sales_count')->default(0)->after('sales_count');
            $table->decimal('delivery_revenue', 12, 2)->default(0)->after('delivery_sales_count');
            $table->unsignedInteger('counter_sales_count')->default(0)->after('delivery_revenue');
            $table->decimal('counter_revenue', 12, 2)->default(0)->after('counter_sales_count');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_revenues', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_sales_count',
                'delivery_revenue',
                'counter_sales_count',
                'counter_revenue',
            ]);
        });
    }
};
