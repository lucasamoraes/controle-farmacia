<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->date('worked_date')->nullable()->after('event_type');
            $table->boolean('paid_outside')->default(false)->after('worked_date');
            $table->date('paid_at')->nullable()->after('paid_outside');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->dropColumn(['worked_date', 'paid_outside', 'paid_at']);
        });
    }
};
