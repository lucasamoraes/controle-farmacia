<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employee_advances', 'deduct_month')) {
            Schema::table('employee_advances', function (Blueprint $table) {
                $table->date('deduct_month')->nullable()->after('advance_date')->index();
            });
        }

        DB::table('employee_advances')
            ->whereNull('deduct_month')
            ->orderBy('id')
            ->get(['id', 'advance_date'])
            ->each(function ($row) {
                DB::table('employee_advances')
                    ->where('id', $row->id)
                    ->update([
                        'deduct_month' => Carbon::parse($row->advance_date)->addMonthNoOverflow()->startOfMonth()->toDateString(),
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_advances', 'deduct_month')) {
            Schema::table('employee_advances', function (Blueprint $table) {
                $table->dropColumn('deduct_month');
            });
        }
    }
};
