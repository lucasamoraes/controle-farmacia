<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->after('company_id');
            $table->string('cbo_code', 20)->nullable()->after('role');
            $table->string('department')->nullable()->after('cbo_code');
            $table->string('branch')->nullable()->after('department');
            $table->decimal('base_salary', 12, 2)->default(0)->after('variable_salary');
            $table->decimal('inss_salary', 12, 2)->default(0)->after('base_salary');
            $table->decimal('fgts_base', 12, 2)->default(0)->after('inss_salary');
            $table->decimal('fgts_month', 12, 2)->default(0)->after('fgts_base');
            $table->decimal('irrf_base', 12, 2)->default(0)->after('fgts_month');
            $table->decimal('irrf_bracket', 12, 2)->default(0)->after('irrf_base');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_code',
                'cbo_code',
                'department',
                'branch',
                'base_salary',
                'inss_salary',
                'fgts_base',
                'fgts_month',
                'irrf_base',
                'irrf_bracket',
            ]);
        });
    }
};
