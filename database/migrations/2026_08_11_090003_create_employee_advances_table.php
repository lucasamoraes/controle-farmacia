<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('advance_date');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->default('pix');
            $table->timestamps();

            $table->index(['employee_id', 'advance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
