<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_invoice_items', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('amount');
            $table->unsignedTinyInteger('recurrence_day')->nullable()->after('is_recurring');
            $table->date('recurrence_start_month')->nullable()->after('recurrence_day');
            $table->date('recurrence_end_month')->nullable()->after('recurrence_start_month');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'recurrence_day', 'recurrence_start_month', 'recurrence_end_month']);
        });
    }
};
