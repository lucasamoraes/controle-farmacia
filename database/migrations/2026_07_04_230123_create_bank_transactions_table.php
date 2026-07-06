<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_payable_id')->nullable()->constrained('payables')->nullOnDelete();
            $table->date('transaction_date')->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('type')->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
