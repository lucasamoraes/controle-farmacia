<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_revenues', function (Blueprint $table) {
            $table->decimal('revenue_to_receive', 12, 2)->default(0)->after('gross_revenue');
            $table->decimal('cmv_percentage', 5, 2)->default(0)->after('cost_of_goods_sold');
            $table->decimal('items_per_ticket', 8, 2)->default(0)->after('average_ticket');
            $table->text('important_info')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_revenues', function (Blueprint $table) {
            $table->dropColumn([
                'revenue_to_receive',
                'cmv_percentage',
                'items_per_ticket',
                'important_info',
            ]);
        });
    }
};
