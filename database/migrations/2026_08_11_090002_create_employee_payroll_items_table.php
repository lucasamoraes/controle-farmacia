<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->string('code')->nullable();
            $table->string('description');
            $table->string('reference')->nullable();
            $table->decimal('earning', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_items');
    }
};
