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
