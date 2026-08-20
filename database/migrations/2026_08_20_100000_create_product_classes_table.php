<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        $classes = [
            'SIMILARES',
            'ETICOS',
            'PERFUMARIA',
            'SUPLEMENTOS E VITAM',
            'GENERICOS',
            'CONVENIENCIA',
            'LEITES',
            'HOSPITALAR',
            'FRALDAS',
            'SERVICOS',
        ];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($classes as $class) {
                DB::table('product_classes')->insert([
                    'company_id' => $companyId,
                    'name' => $class,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_classes');
    }
};
