<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRecurringExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_generate_monthly_employee_expenses(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $employee = Employee::create([
            'company_id' => $company->id,
            'name' => 'Ana Caixa',
            'role' => 'Caixa',
            'salary' => 2800,
            'fixed_salary' => 2500,
            'variable_salary' => 300,
            'payment_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post('/funcionarios/gerar-despesas', ['mes' => '2026-08'])
            ->assertRedirect('/funcionarios?mes=2026-08');

        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $company->id,
            'name' => 'Funcionarios',
            'type' => 'expense',
        ]);

        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Folha funcionarios fixa - 08/2026',
            'amount' => 2500,
            'due_date' => '2026-08-05 00:00:00',
            'status' => 'open',
            'source' => 'employee_fixed',
            'document_number' => 'FUNC-FOLHA-FIXA-2026-08',
        ]);

        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Folha funcionarios variavel - 08/2026',
            'amount' => 300,
            'due_date' => '2026-08-05 00:00:00',
            'status' => 'open',
            'source' => 'employee_variable',
            'document_number' => 'FUNC-FOLHA-VARIAVEL-2026-08',
        ]);
    }

    public function test_generating_employee_expenses_twice_does_not_duplicate_payables(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        Employee::create([
            'company_id' => $company->id,
            'name' => 'Bruno Balcao',
            'salary' => 1800,
            'fixed_salary' => 1800,
            'payment_day' => 31,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post('/funcionarios/gerar-despesas', ['mes' => '2026-02']);
        $this->actingAs($owner)->post('/funcionarios/gerar-despesas', ['mes' => '2026-02']);

        $this->assertDatabaseCount('payables', 1);
        $this->assertDatabaseHas('payables', [
            'description' => 'Folha funcionarios fixa - 02/2026',
            'due_date' => '2026-02-28 00:00:00',
        ]);
    }

    public function test_finance_user_can_mark_monthly_payroll_as_paid(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        Employee::create([
            'company_id' => $company->id,
            'name' => 'Carla Gerente',
            'salary' => 3200,
            'fixed_salary' => 3200,
            'payment_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($finance)->post('/funcionarios/gerar-despesas', ['mes' => '2026-08']);

        $this->actingAs($finance)
            ->patch('/funcionarios/pagar-folha', ['mes' => '2026-08'])
            ->assertRedirect('/funcionarios?mes=2026-08');

        $this->assertDatabaseHas('payables', [
            'description' => 'Folha funcionarios fixa - 08/2026',
            'status' => 'paid',
        ]);
    }

    public function test_viewer_can_view_employees_but_cannot_generate_expenses(): void
    {
        [, $viewer] = $this->companyWithUser('viewer');

        $this->actingAs($viewer)
            ->get('/funcionarios')
            ->assertOk()
            ->assertDontSee('Novo funcionario');

        $this->actingAs($viewer)
            ->post('/funcionarios/gerar-despesas', ['mes' => '2026-08'])
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
