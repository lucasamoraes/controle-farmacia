<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_manage_employee_receipt_events_and_advances(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => '8',
            'name' => 'Ainquele Mirian',
            'role' => 'Atendente de Farmacia',
            'cbo_code' => '521130',
            'department' => '1',
            'branch' => '1',
            'salary' => 1621,
            'fixed_salary' => 1621,
            'base_salary' => 1621,
            'inss_salary' => 2621,
            'fgts_base' => 2621,
            'fgts_month' => 209.68,
            'irrf_base' => 2013.80,
            'irrf_bracket' => 0,
            'payment_day' => 5,
            'starts_on' => '2025-09-08',
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post("/funcionarios/{$employee->id}/recibo/eventos", [
                'reference_month' => '2026-06',
                'event_type' => 'bonus',
                'code' => '1',
                'description' => 'GRATIFICACOES',
                'reference' => '1.000,00',
                'earning' => 1000,
                'deduction' => 0,
            ])
            ->assertRedirect("/funcionarios/{$employee->id}/recibo?mes=2026-06");

        $this->actingAs($finance)
            ->post("/funcionarios/{$employee->id}/vales", [
                'advance_date' => '2026-05-15',
                'deduct_month' => '2026-06',
                'description' => 'Vale transporte',
                'amount' => 100,
                'payment_method' => 'pix',
            ])
            ->assertRedirect("/funcionarios/{$employee->id}/recibo?mes=2026-06");

        $this->actingAs($finance)
            ->get("/funcionarios/{$employee->id}/recibo?mes=2026-06")
            ->assertOk()
            ->assertSee('Ainquele Mirian')
            ->assertSee('Atendente de Farmacia')
            ->assertSee('521130')
            ->assertSee('SALARIO BASE')
            ->assertSee('GRATIFICACOES')
            ->assertSee('Vale transporte')
            ->assertSee('R$ 1.621,00')
            ->assertSee('R$ 221,58')
            ->assertSee('R$ 2.399,42');

        $this->assertDatabaseHas('employee_payroll_items', [
            'employee_id' => $employee->id,
            'description' => 'GRATIFICACOES',
            'earning' => 1000,
        ]);
        $this->assertDatabaseHas('employee_advances', [
            'employee_id' => $employee->id,
            'description' => 'Vale transporte',
            'payment_method' => 'pix',
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
