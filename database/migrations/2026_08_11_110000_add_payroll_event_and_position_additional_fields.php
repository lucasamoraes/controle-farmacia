<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->string('event_type')->default('other_earning')->after('reference_month');
        });

        Schema::table('employee_positions', function (Blueprint $table) {
            $table->string('additional_type')->nullable()->after('cbo_code');
            $table->decimal('additional_percent', 5, 2)->nullable()->after('additional_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });

        Schema::table('employee_positions', function (Blueprint $table) {
            $table->dropColumn(['additional_type', 'additional_percent']);
        });
    }
};
