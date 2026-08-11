<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_invoices', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->after('payable_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_id');
        });
    }
};
