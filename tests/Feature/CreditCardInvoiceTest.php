<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_create_credit_card_invoice_with_category_items(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $marketing = FinancialCategory::create(['company_id' => $company->id, 'name' => 'Marketing', 'type' => 'expense']);
        $fees = FinancialCategory::create(['company_id' => $company->id, 'name' => 'Taxas Bancarias', 'type' => 'expense']);

        $this->actingAs($finance)
            ->post('/faturas-cartao', [
                'card_name' => 'Visa Farmacia',
                'reference_month' => '2026-08',
                'due_date' => '2026-08-20',
                'status' => 'open',
                'items' => [
                    ['description' => 'Anuncios', 'financial_category_id' => $marketing->id, 'amount' => 300],
                    ['description' => 'Tarifas', 'financial_category_id' => $fees->id, 'amount' => 50],
                ],
            ])
            ->assertRedirect('/faturas-cartao');

        $this->assertDatabaseHas('credit_card_invoices', [
            'company_id' => $company->id,
            'card_name' => 'Visa Farmacia',
            'reference_month' => '2026-08-01 00:00:00',
            'total_amount' => 350,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Fatura cartao - Visa Farmacia - 08/2026',
            'amount' => 350,
            'source' => 'credit_card_invoice',
        ]);
        $this->assertDatabaseHas('credit_card_invoice_items', [
            'financial_category_id' => $marketing->id,
            'description' => 'Anuncios',
            'amount' => 300,
        ]);
    }

    public function test_dashboard_category_breakdown_uses_credit_card_invoice_items(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        $marketing = FinancialCategory::create(['company_id' => $company->id, 'name' => 'Marketing', 'type' => 'expense']);
        $fees = FinancialCategory::create(['company_id' => $company->id, 'name' => 'Taxas Bancarias', 'type' => 'expense']);

        $this->actingAs($owner)->post('/faturas-cartao', [
            'card_name' => 'Master',
            'reference_month' => '2026-08',
            'due_date' => '2026-08-10',
            'status' => 'open',
            'items' => [
                ['description' => 'Campanha', 'financial_category_id' => $marketing->id, 'amount' => 700],
                ['description' => 'Tarifa', 'financial_category_id' => $fees->id, 'amount' => 80],
            ],
        ]);

        $this->actingAs($owner)
            ->get('/dashboard?periodo=custom&inicio=2026-08-01&fim=2026-08-31')
            ->assertOk()
            ->assertSee('Marketing')
            ->assertSee('Taxas Bancarias')
            ->assertDontSee('Cartao de credito</span>', false);
    }

    public function test_owner_can_create_category_in_settings(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');

        $this->actingAs($owner)
            ->post('/configuracoes/categorias', [
                'name' => 'Manutencao',
                'type' => 'expense',
            ])
            ->assertRedirect('/configuracoes/categorias');

        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $company->id,
            'name' => 'Manutencao',
            'type' => 'expense',
            'is_active' => true,
        ]);
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
