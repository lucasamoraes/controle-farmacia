<?php

namespace Tests\Feature;

use App\Models\BoletoUpload;
use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoletoSupplierCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_boleto_can_create_supplier_from_cnpj_data(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);
        $boleto = BoletoUpload::create([
            'company_id' => $company->id,
            'original_file_name' => 'boleto.pdf',
            'stored_path' => 'boletos/teste.pdf',
            'processing_status' => 'review',
            'parsed_data' => [
                'document' => '11222333000144',
                'beneficiary_name' => 'DISTRIBUIDORA TESTE LTDA',
                'cnpj_lookup' => [
                    'document' => '11222333000144',
                    'name' => 'DISTRIBUIDORA TESTE LTDA',
                    'trade_name' => 'DISTRIBUIDORA TESTE',
                    'legal_status' => 'ATIVA',
                    'city' => 'RIBEIRAO PRETO',
                    'state' => 'SP',
                ],
            ],
        ]);

        $this->actingAs($user)->post("/boletos/{$boleto->id}/confirmar", [
            'create_supplier' => '1',
            'financial_category_id' => $category->id,
            'description' => 'DISTRIBUIDORA TESTE LTDA',
            'amount' => '200.00',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect('/contas-a-pagar');

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'document' => '11222333000144',
            'name' => 'DISTRIBUIDORA TESTE LTDA',
            'legal_status' => 'ATIVA',
        ]);

        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'source' => 'boleto_pdf',
        ]);
    }
}
