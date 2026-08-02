<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Payable;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_search_filters_by_name(): void
    {
        [$user, $company, $category] = $this->context();

        Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'Distribuidora Alfa',
            'is_active' => true,
        ]);
        Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'Fornecedor Beta',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/fornecedores?busca=Alfa')
            ->assertOk()
            ->assertSee('Distribuidora Alfa')
            ->assertDontSee('Fornecedor Beta');
    }

    public function test_inactive_supplier_can_be_reactivated(): void
    {
        [$user, $company, $category] = $this->context();
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'Fornecedor Inativo',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->patch("/fornecedores/{$supplier->id}/reativar")
            ->assertRedirect('/fornecedores');

        $this->assertTrue($supplier->fresh()->is_active);
    }

    public function test_payable_search_and_period_filters_results(): void
    {
        [$user, $company, $category] = $this->context();
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'Medicamenta Sul',
            'is_active' => true,
        ]);

        Payable::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'financial_category_id' => $category->id,
            'description' => 'Compra Medicamenta',
            'amount' => 100,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'open',
            'source' => 'manual',
        ]);
        Payable::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'description' => 'Aluguel da loja',
            'amount' => 200,
            'due_date' => now()->subDays(20)->toDateString(),
            'status' => 'open',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/contas-a-pagar?busca=Medicamenta&periodo=7')
            ->assertOk()
            ->assertSee('Compra Medicamenta')
            ->assertDontSee('Aluguel da loja');
    }

    public function test_payable_custom_dates_filter_by_due_date_without_period_select(): void
    {
        [$user, $company, $category] = $this->context();

        Payable::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'description' => 'Conta dentro do intervalo',
            'amount' => 100,
            'due_date' => '2026-07-10',
            'status' => 'open',
            'source' => 'manual',
        ]);
        Payable::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'description' => 'Conta fora do intervalo',
            'amount' => 200,
            'due_date' => '2026-08-10',
            'status' => 'open',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/contas-a-pagar?inicio=2026-07-01&fim=2026-07-31')
            ->assertOk()
            ->assertSee('Conta dentro do intervalo')
            ->assertDontSee('Conta fora do intervalo');
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);

        return [$user, $company, $category];
    }
}
