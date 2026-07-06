<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boleto_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payable_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_file_name');
            $table->string('stored_path');
            $table->longText('extracted_text')->nullable();
            $table->json('parsed_data')->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boleto_uploads');
    }
};
