<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payable_id')->nullable()->constrained()->nullOnDelete();
            $table->string('card_name');
            $table->date('reference_month');
            $table->date('due_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoices');
    }
};
