<?php

namespace Tests\Feature;

use App\Models\BoletoUpload;
use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Payable;
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

    public function test_user_can_correct_supplier_document_before_confirming_boleto(): void
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
                'document' => '00000000000000',
                'beneficiary_name' => 'DISTRIBUIDORA CORRIGIDA LTDA',
            ],
        ]);

        $this->actingAs($user)->post("/boletos/{$boleto->id}/confirmar", [
            'create_supplier' => '1',
            'document' => '11.222.333/0001-44',
            'financial_category_id' => $category->id,
            'description' => 'DISTRIBUIDORA CORRIGIDA LTDA',
            'amount' => '200.00',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect('/contas-a-pagar');

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'document' => '11222333000144',
            'name' => 'DISTRIBUIDORA CORRIGIDA LTDA',
        ]);

        $this->assertSame('11222333000144', $boleto->fresh()->parsed_data['document']);
    }

    public function test_review_warns_about_possible_duplicate_boleto(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'DISTRIBUIDORA TESTE LTDA',
            'document' => '11222333000144',
        ]);
        Payable::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'description' => 'Boleto ja cadastrado',
            'amount' => 200,
            'due_date' => '2026-07-22',
            'status' => 'open',
            'source' => 'boleto_pdf',
            'digitable_line' => '00190500954014481606906809350314337370000020000',
        ]);
        $boleto = BoletoUpload::create([
            'company_id' => $company->id,
            'original_file_name' => 'boleto.pdf',
            'stored_path' => 'boletos/teste.pdf',
            'processing_status' => 'review',
            'parsed_data' => [
                'document' => '11222333000144',
                'amount' => '200.00',
                'due_date' => '2026-07-22',
                'digitable_line' => '00190500954014481606906809350314337370000020000',
            ],
        ]);

        $this->actingAs($user)
            ->get("/boletos/{$boleto->id}/revisar")
            ->assertOk()
            ->assertSee('Possivel boleto duplicado')
            ->assertSee('Boleto ja cadastrado');
    }
}
