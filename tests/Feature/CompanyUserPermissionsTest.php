<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyUserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_existing_user_to_company(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        $newUser = User::factory()->create(['email' => 'financeiro@example.com']);

        $this->actingAs($owner)
            ->post('/usuarios', [
                'email' => 'financeiro@example.com',
                'role' => 'finance',
            ])
            ->assertRedirect('/usuarios');

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $newUser->id,
            'role' => 'finance',
        ]);
    }

    public function test_finance_user_cannot_manage_users(): void
    {
        [, $finance] = $this->companyWithUser('finance');

        $this->actingAs($finance)
            ->get('/usuarios')
            ->assertForbidden();
    }

    public function test_owner_can_create_complete_demo_pharmacy(): void
    {
        [, $owner] = $this->companyWithUser('owner');

        $this->actingAs($owner)
            ->post('/usuarios/ambiente-demonstracao', [
                'demo_name' => 'Visitante Teste',
                'demo_email' => 'visitante@farmateste.com.br',
                'demo_password' => 'SenhaDemo#2026',
                'demo_password_confirmation' => 'SenhaDemo#2026',
            ])
            ->assertRedirect('/usuarios');

        $demoCompany = Company::where('document', 'DEMO-FARMA-2026')->firstOrFail();
        $demoUser = User::where('email', 'visitante@farmateste.com.br')->firstOrFail();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $demoCompany->id,
            'user_id' => $demoUser->id,
            'role' => 'owner',
        ]);
        $this->assertSame(12, $demoCompany->monthlyRevenues()->count());
        $this->assertSame(365, $demoCompany->dailySales()->count());
        $this->assertSame(6, $demoCompany->employees()->count());
        $this->assertSame(24, $demoCompany->products()->count());
        $this->assertSame(2, $demoCompany->purchaseLists()->count());
        $this->assertSame(48, $demoCompany->marketingCampaignMetrics()->count());
        $this->assertGreaterThan(100, $demoCompany->payables()->count());
        $this->assertEquals(112500.00, (float) $demoCompany->monthlyRevenues()->whereDate('reference_month', '2026-01-01')->value('gross_revenue'));
        $this->assertEquals(194700.00, (float) $demoCompany->monthlyRevenues()->whereDate('reference_month', '2026-10-01')->value('gross_revenue'));
    }

    public function test_finance_user_cannot_create_demo_pharmacy(): void
    {
        [, $finance] = $this->companyWithUser('finance');

        $this->actingAs($finance)
            ->post('/usuarios/ambiente-demonstracao', [
                'demo_name' => 'Sem Permissao',
                'demo_email' => 'sempermissao@farmateste.com.br',
                'demo_password' => 'SenhaDemo#2026',
                'demo_password_confirmation' => 'SenhaDemo#2026',
            ])
            ->assertForbidden();
    }

    public function test_viewer_can_read_lists_but_cannot_write_financial_data(): void
    {
        [$company, $viewer] = $this->companyWithUser('viewer');
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
            'is_default' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/fornecedores')
            ->assertOk()
            ->assertDontSee('Novo fornecedor');

        $this->actingAs($viewer)
            ->post('/fornecedores', [
                'name' => 'Distribuidora Bloqueada',
                'financial_category_id' => $category->id,
            ])
            ->assertForbidden();
    }

    public function test_finance_user_can_create_supplier(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
            'is_default' => true,
        ]);

        $this->actingAs($finance)
            ->post('/fornecedores', [
                'name' => 'Distribuidora Permitida',
                'financial_category_id' => $category->id,
            ])
            ->assertRedirect('/fornecedores');

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'name' => 'Distribuidora Permitida',
        ]);
    }

    public function test_buyer_can_only_access_purchase_lists(): void
    {
        [, $buyer] = $this->companyWithUser('buyer');

        $this->actingAs($buyer)->get('/listas-compras')->assertOk();
        $this->actingAs($buyer)->get('/dashboard')->assertForbidden();
        $this->actingAs($buyer)->get('/contas-a-pagar')->assertForbidden();
        $this->actingAs($buyer)->get('/fornecedores')->assertForbidden();
        $this->actingAs($buyer)->get('/funcionarios')->assertForbidden();
        $this->actingAs($buyer)->get('/produtos')->assertForbidden();
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
