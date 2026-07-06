<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('gross_revenue', 12, 2)->default(0);
            $table->decimal('cost_of_goods_sold', 12, 2)->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('average_ticket', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_revenues');
    }
};
