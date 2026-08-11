<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollItem;
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

    public function test_sunday_or_holiday_work_can_be_paid_outside_or_added_to_payroll(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $employee = Employee::create([
            'company_id' => $company->id,
            'name' => 'Joao Balcao',
            'salary' => 1800,
            'fixed_salary' => 1800,
            'base_salary' => 1800,
            'payment_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post("/funcionarios/{$employee->id}/recibo/eventos", [
                'reference_month' => '2026-08',
                'event_type' => 'sunday_work',
                'worked_date' => '2026-08-09',
                'paid_outside' => 1,
                'description' => 'Trabalho domingo',
                'earning' => 120,
            ])
            ->assertRedirect("/funcionarios/{$employee->id}/recibo?mes=2026-08");

        $this->actingAs($finance)
            ->post("/funcionarios/{$employee->id}/recibo/eventos", [
                'reference_month' => '2026-08',
                'event_type' => 'holiday_work',
                'worked_date' => '2026-08-15',
                'description' => 'Trabalho feriado',
                'earning' => 150,
            ])
            ->assertRedirect("/funcionarios/{$employee->id}/recibo?mes=2026-08");

        $this->assertDatabaseHas('employee_payroll_items', [
            'employee_id' => $employee->id,
            'event_type' => 'sunday_work',
            'worked_date' => '2026-08-09 00:00:00',
            'paid_outside' => true,
            'earning' => 120,
        ]);
        $this->assertDatabaseHas('employee_payroll_items', [
            'employee_id' => $employee->id,
            'event_type' => 'holiday_work',
            'worked_date' => '2026-08-15 00:00:00',
            'paid_outside' => false,
            'earning' => 150,
        ]);
        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Trabalho domingo - Joao Balcao - 09/08/2026',
            'amount' => 120,
            'status' => 'paid',
            'source' => 'employee_extra_paid',
        ]);
    }

    public function test_payroll_item_update_recalculates_monthly_payroll_payable(): void
    {
        [$company, $finance] = $this->companyWithUser('finance');
        $employee = Employee::create([
            'company_id' => $company->id,
            'name' => 'Maria Caixa',
            'salary' => 2000,
            'fixed_salary' => 2000,
            'base_salary' => 2000,
            'payment_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($finance)->post('/funcionarios/gerar-despesas', ['mes' => '2026-03']);

        $item = EmployeePayrollItem::create([
            'employee_id' => $employee->id,
            'reference_month' => '2026-03-01',
            'event_type' => 'bonus',
            'description' => 'Bonus',
            'earning' => 100,
            'deduction' => 0,
        ]);

        $this->actingAs($finance)
            ->put("/funcionarios/recibo/eventos/{$item->id}", [
                'reference_month' => '2026-03',
                'event_type' => 'bonus',
                'description' => 'Bonus ajustado',
                'earning' => 300,
                'deduction' => 0,
            ])
            ->assertRedirect("/funcionarios/{$employee->id}/recibo?mes=2026-03");

        $this->assertDatabaseHas('employee_payroll_items', [
            'id' => $item->id,
            'description' => 'Bonus ajustado',
            'earning' => 300,
        ]);
        $this->assertDatabaseHas('payables', [
            'company_id' => $company->id,
            'description' => 'Folha funcionarios - 03/2026',
            'amount' => 2144.31,
            'source' => 'employee_recurring',
            'document_number' => 'FUNC-FOLHA-2026-03',
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
