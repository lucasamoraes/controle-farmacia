<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySalesManualEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_register_daily_sale_and_sync_monthly_revenue(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');

        $this->actingAs($finance)
            ->post('/importar/vendas-diarias/manual', [
                'sale_date' => '2026-08-04',
                'delivery_sales_count' => 5,
                'delivery_revenue' => 500.25,
                'counter_sales_count' => 8,
                'counter_revenue' => 1000.50,
            ])
            ->assertRedirect('/importar/vendas-diarias');

        $this->assertDatabaseHas('daily_sales', [
            'company_id' => $company->id,
            'sale_date' => '2026-08-04 00:00:00',
            'amount' => 1500.75,
            'delivery_sales_count' => 5,
            'delivery_revenue' => 500.25,
            'counter_sales_count' => 8,
            'counter_revenue' => 1000.50,
        ]);

        $this->assertDatabaseHas('monthly_revenues', [
            'company_id' => $company->id,
            'reference_month' => '2026-08-01 00:00:00',
            'gross_revenue' => 1500.75,
            'delivery_sales_count' => 5,
            'delivery_revenue' => 500.25,
            'counter_sales_count' => 8,
            'counter_revenue' => 1000.50,
            'sales_count' => 13,
        ]);
    }

    public function test_registering_same_daily_sale_date_updates_value_and_recalculates_month(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');

        $this->actingAs($owner)->post('/importar/vendas-diarias/manual', [
            'sale_date' => '2026-08-04',
            'delivery_revenue' => 400,
            'counter_revenue' => 600,
        ]);
        $this->actingAs($owner)->post('/importar/vendas-diarias/manual', [
            'sale_date' => '2026-08-05',
            'delivery_revenue' => 200,
            'counter_revenue' => 300,
        ]);
        $this->actingAs($owner)->post('/importar/vendas-diarias/manual', [
            'sale_date' => '2026-08-04',
            'delivery_revenue' => 500,
            'counter_revenue' => 700,
        ]);

        $this->assertDatabaseCount('daily_sales', 2);
        $this->assertDatabaseHas('daily_sales', [
            'company_id' => $company->id,
            'sale_date' => '2026-08-04 00:00:00',
            'amount' => 1200,
        ]);
        $this->assertDatabaseHas('monthly_revenues', [
            'company_id' => $company->id,
            'reference_month' => '2026-08-01 00:00:00',
            'gross_revenue' => 1700,
        ]);
    }

    public function test_finance_user_can_edit_recent_daily_sale_and_resync_months(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');

        $this->actingAs($finance)->post('/importar/vendas-diarias/manual', [
            'sale_date' => '2026-08-31',
            'delivery_revenue' => 400,
            'counter_revenue' => 600,
        ]);

        $sale = $company->dailySales()->firstOrFail();

        $this->actingAs($finance)
            ->put("/importar/vendas-diarias/{$sale->id}", [
                'sale_date' => '2026-09-01',
                'delivery_sales_count' => 3,
                'delivery_revenue' => 300,
                'counter_sales_count' => 7,
                'counter_revenue' => 900,
            ])
            ->assertRedirect('/importar/vendas-diarias');

        $this->assertDatabaseHas('daily_sales', [
            'id' => $sale->id,
            'sale_date' => '2026-09-01 00:00:00',
            'amount' => 1200,
            'delivery_sales_count' => 3,
            'counter_sales_count' => 7,
        ]);
        $this->assertDatabaseHas('monthly_revenues', [
            'company_id' => $company->id,
            'reference_month' => '2026-08-01 00:00:00',
            'gross_revenue' => 0,
        ]);
        $this->assertDatabaseHas('monthly_revenues', [
            'company_id' => $company->id,
            'reference_month' => '2026-09-01 00:00:00',
            'gross_revenue' => 1200,
            'sales_count' => 10,
        ]);
    }

    public function test_viewer_cannot_register_daily_sale(): void
    {
        [, $viewer] = $this->companyWithUser('viewer');

        $this->actingAs($viewer)
            ->post('/importar/vendas-diarias/manual', [
                'sale_date' => '2026-08-04',
                'delivery_revenue' => 100,
            ])
            ->assertForbidden();
    }

    private function companyWithUser(string $role): array
    {
        $company = Company::create([
            'name' => 'Farmacia Teste',
            'trade_name' => 'Farmacia Teste',
        ]);
        $user = User::factory()->create();

        $company->users()->attach($user->id, ['role' => $role]);

        return [$company, $user];
    }
}
