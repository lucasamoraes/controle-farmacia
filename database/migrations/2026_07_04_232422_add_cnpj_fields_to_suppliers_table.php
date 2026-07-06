<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('legal_status')->nullable()->after('document');
            $table->string('street')->nullable()->after('phone');
            $table->string('number')->nullable()->after('street');
            $table->string('district')->nullable()->after('number');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('zip_code', 12)->nullable()->after('state');
            $table->string('main_activity')->nullable()->after('zip_code');
            $table->timestamp('cnpj_checked_at')->nullable()->after('main_activity');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'legal_status',
                'street',
                'number',
                'district',
                'city',
                'state',
                'zip_code',
                'main_activity',
                'cnpj_checked_at',
            ]);
        });
    }
};
