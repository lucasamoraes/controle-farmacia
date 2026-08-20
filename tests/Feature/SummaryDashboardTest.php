<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            'reference_month' => '2026-07-01 00:00:00',
            'gross_revenue' => 10000,
            'sales_count' => 250,
            'delivery_sales_count' => 50,
            'delivery_revenue' => 3000,
            'counter_sales_count' => 200,
            'counter_revenue' => 7000,
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
            ->assertSee('Consulta rapida por periodo')
            ->assertSee('Necessidade de caixa')
            ->assertSee('Media diaria faturamento')
            ->assertSee('A pagar no periodo')
            ->assertSee('R$ 10.000,00')
            ->assertSee('R$ 312,50')
            ->assertSee('25,0%')
            ->assertSee('R$ 2.500,00');
    }

    public function test_monthly_revenue_form_calculates_totals_from_channels(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->post('/faturamento-mensal', [
                'reference_month' => '2026-07',
                'gross_revenue' => 1,
                'sales_count' => 1,
                'delivery_sales_count' => 10,
                'delivery_revenue' => 500,
                'counter_sales_count' => 30,
                'counter_revenue' => 1500,
            ])
            ->assertRedirect('/resumo?mes=2026-07');

        $this->assertDatabaseHas('monthly_revenues', [
            'company_id' => $company->id,
            'reference_month' => '2026-07-01 00:00:00',
            'gross_revenue' => 2000,
            'sales_count' => 40,
            'delivery_sales_count' => 10,
            'counter_sales_count' => 30,
            'average_ticket' => 50,
        ]);
    }

    public function test_dashboard_displays_due_today_and_expense_ratio_alerts(): void
    {
        Carbon::setTestNow('2026-07-07');
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);

        $company->monthlyRevenues()->create([
            'reference_month' => '2026-06-01',
            'gross_revenue' => 10000,
            'sales_count' => 200,
            'average_ticket' => 50,
        ]);
        $company->payables()->create([
            'financial_category_id' => $category->id,
            'description' => 'Boleto vencendo hoje',
            'amount' => 5600,
            'due_date' => '2026-07-07',
            'status' => 'open',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Boletos vencendo hoje')
            ->assertSee('Atenção: despesas acima de 55%')
            ->assertSee('56,0%');

        Carbon::setTestNow();
    }

    public function test_expense_ratio_alert_uses_only_merchandise_purchases(): void
    {
        Carbon::setTestNow('2026-07-07');
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $merchandise = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);
        $rent = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Aluguel',
            'type' => 'expense',
        ]);

        $company->monthlyRevenues()->create([
            'reference_month' => '2026-06-01',
            'gross_revenue' => 10000,
            'sales_count' => 200,
            'average_ticket' => 50,
        ]);
        $company->payables()->create([
            'financial_category_id' => $merchandise->id,
            'description' => 'Compra estoque',
            'amount' => 3000,
            'due_date' => '2026-07-10',
            'status' => 'open',
            'source' => 'manual',
        ]);
        $company->payables()->create([
            'financial_category_id' => $rent->id,
            'description' => 'Aluguel',
            'amount' => 6000,
            'due_date' => '2026-07-10',
            'status' => 'open',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('compras de mercadorias')
            ->assertSee('30,0%')
            ->assertDontSee('90,0%');

        Carbon::setTestNow();
    }
}
