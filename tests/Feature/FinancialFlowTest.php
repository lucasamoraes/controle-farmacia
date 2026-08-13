<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Payable;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_company_and_create_financial_records(): void
    {
        $this->post('/cadastro', [
            'name' => 'Lucas',
            'email' => 'lucas@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'company_name' => 'Farmacia Modelo',
            'company_document' => '12345678000199',
        ])->assertRedirect('/dashboard');

        $company = Company::first();
        $category = FinancialCategory::where('company_id', $company->id)->where('type', 'expense')->first();

        $this->post('/fornecedores', [
            'name' => 'Distribuidora Teste',
            'document' => '11222333000144',
            'financial_category_id' => $category->id,
        ])->assertRedirect('/fornecedores');

        $supplier = Supplier::first();

        $this->post('/contas-a-pagar', [
            'supplier_id' => $supplier->id,
            'financial_category_id' => $category->id,
            'description' => 'Boleto de compra',
            'amount' => '1500.50',
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertRedirect('/contas-a-pagar');

        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'description' => 'Boleto de compra',
            'status' => 'open',
        ]);
    }

    public function test_user_can_create_recurring_payables_until_selected_month(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Aluguel',
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->post('/contas-a-pagar', [
                'financial_category_id' => $category->id,
                'description' => 'Aluguel da loja',
                'amount' => 1000,
                'due_date' => '2026-08-10',
                'is_recurring' => 1,
                'recurrence_end_month' => '2026-12',
            ])
            ->assertRedirect('/contas-a-pagar');

        $this->assertDatabaseCount('payables', 5);
        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Aluguel da loja - 12/2026',
            'due_date' => '2026-12-10 00:00:00',
            'amount' => 1000,
        ]);
    }
}
