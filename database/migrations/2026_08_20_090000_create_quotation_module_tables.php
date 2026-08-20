<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('ean')->nullable();
            $table->string('group')->nullable();
            $table->string('class')->nullable();
            $table->decimal('last_purchase_price', 12, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'ean']);
            $table->index(['company_id', 'description']);
        });

        Schema::create('purchase_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('started_quotation_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('ean')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->default('un');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['purchase_list_id', 'ean']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique('purchase_list_id');
            $table->index(['company_id', 'status']);
        });

        Schema::create('quotation_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['quotation_id', 'supplier_id']);
        });

        Schema::create('quotation_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_list_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['quotation_id', 'purchase_list_item_id', 'supplier_id'], 'quotation_price_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_prices');
        Schema::dropIfExists('quotation_suppliers');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('purchase_list_items');
        Schema::dropIfExists('purchase_lists');
        Schema::dropIfExists('products');
    }
};
