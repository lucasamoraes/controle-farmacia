<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('fixed_salary', 12, 2)->default(0)->after('role');
            $table->decimal('variable_salary', 12, 2)->default(0)->after('fixed_salary');
        });

        DB::table('employees')->update([
            'fixed_salary' => DB::raw('salary'),
        ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['fixed_salary', 'variable_salary']);
        });
    }
};
