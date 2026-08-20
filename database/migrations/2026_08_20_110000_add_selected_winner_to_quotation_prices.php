<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_prices', function (Blueprint $table) {
            $table->boolean('is_selected_winner')->default(false)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_prices', function (Blueprint $table) {
            $table->dropColumn('is_selected_winner');
        });
    }
};
