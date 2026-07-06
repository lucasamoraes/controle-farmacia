<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_displays_monthly_revenue_and_expenses(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'financial_category_id' => $category->id,
            'name' => 'GRUPO SC',
        ]);

        $company->monthlyRevenues()->create([
            'reference_month' => '2026-07-01',
            'gross_revenue' => 10000,
            'sales_count' => 250,
            'average_ticket' => 40,
            'cmv_percentage' => 55,
        ]);
        $company->payables()->create([
            'supplier_id' => $supplier->id,
            'financial_category_id' => $category->id,
            'description' => 'Compra estoque',
            'amount' => 2500,
            'due_date' => '2026-07-10',
            'status' => 'open',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/resumo?mes=2026-07')
            ->assertOk()
            ->assertSee('Resumo financeiro')
            ->assertSee('GRUPO SC')
            ->assertSee('Compra de mercadoria')
            ->assertSee('R$ 10.000,00')
            ->assertSee('R$ 2.500,00');
    }
}
