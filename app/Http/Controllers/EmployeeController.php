<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeMovementType;
use App\Models\EmployeePayrollItem;
use App\Models\FinancialCategory;
use App\Services\PayrollCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, PayrollCalculator $calculator): View
    {
        $company = $this->company();
        $category = $this->employeeCategory($company);
        $month = $this->validMonth($request->query('mes')) ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $search = trim((string) $request->query('busca', ''));
        $status = $request->query('status', 'active');
        $this->syncPayrollForecast($company, $monthStart);

        $employees = $company->employees()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $monthExpenses = $company->payables()
            ->with('category')
            ->where('financial_category_id', $category->id)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'paid' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->get();

        $monthNetPayrollTotal = $this->monthlyPayrollAmount($company, $monthStart, $calculator);
        $employeeNetAmounts = $employees->getCollection()
            ->mapWithKeys(fn (Employee $employee) => [
                $employee->id => $this->employeeMonthlyNetAmount($employee, $monthStart, $calculator),
            ]);

        return view('employees.index', [
            'company' => $company,
            'employees' => $employees,
            'monthExpenses' => $monthExpenses,
            'month' => $month,
            'search' => $search,
            'statusFilter' => $status,
            'activeFixedPayrollTotal' => $company->employees()->where('is_active', true)->sum('base_salary'),
            'monthNetPayrollTotal' => $monthNetPayrollTotal,
            'employeeNetAmounts' => $employeeNetAmounts,
            'activePayrollTotal' => $company->employees()->where('is_active', true)->sum('base_salary'),
            'monthExpenseTotal' => $monthExpenses->where('status', '!=', 'cancelled')->sum('amount'),
            'monthOpenTotal' => $monthExpenses->where('status', 'open')->sum('amount'),
            'monthPaidTotal' => $monthExpenses->where('status', 'paid')->sum('amount'),
        ]);
    }

    public function create(): View
    {
        return view('employees.form', [
            'company' => $this->company(),
            'employee' => new Employee(['payment_day' => 5, 'is_active' => true, 'base_salary' => 0]),
            'positions' => $this->company()->employeePositions()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $this->company()->employeeDepartments()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $company = $this->company();
        $company->employees()->create($this->validated($request, $calculator));
        $this->syncPayrollForecast($company);

        return redirect()->route('funcionarios.index')->with('status', 'Funcionario cadastrado.');
    }

    public function edit(Employee $funcionario): View
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        return view('employees.form', [
            'company' => $company,
            'employee' => $funcionario,
            'positions' => $company->employeePositions()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $company->employeeDepartments()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function receipt(Request $request, Employee $funcionario, PayrollCalculator $calculator): View
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);
        $month = $this->validMonth($request->query('mes')) ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $storedPayrollItems = $funcionario->payrollItems()
            ->whereDate('reference_month', $monthStart->toDateString())
            ->orderBy('code')
            ->orderBy('description')
            ->get();
        $automaticItems = collect($this->automaticPayrollItems($funcionario, $calculator));
        $payrollItems = $automaticItems->concat($storedPayrollItems);
        $advances = $funcionario->advances()
            ->whereDate('deduct_month', $monthStart->toDateString())
            ->orderBy('advance_date')
            ->get();

        return view('employees.receipt', [
            'company' => $company,
            'employee' => $funcionario,
            'month' => $month,
            'payrollItems' => $payrollItems,
            'advances' => $advances,
            'earningsTotal' => $payrollItems->sum('earning'),
            'deductionsTotal' => $payrollItems->sum('deduction') + $advances->sum('amount'),
            'advancesTotal' => $advances->sum('amount'),
            'movementTypes' => $this->ensureDefaultMovementTypes($company)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Employee $funcionario, PayrollCalculator $calculator): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        $funcionario->update($this->validated($request, $calculator));
        $this->syncPayrollForecast($company);

        return redirect()->route('funcionarios.index')->with('status', 'Funcionario atualizado.');
    }

    public function destroy(Employee $funcionario): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        $funcionario->update(['is_active' => false]);

        return redirect()->route('funcionarios.index')->with('status', 'Funcionario inativado.');
    }

    public function restore(Employee $funcionario): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        $funcionario->update(['is_active' => true]);

        return redirect()->route('funcionarios.index')->with('status', 'Funcionario reativado.');
    }

    public function generateMonthlyPayables(Request $request): RedirectResponse
    {
        $company = $this->company();
        $category = $this->employeeCategory($company);
        $data = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
        ]);

        $monthStart = Carbon::createFromFormat('Y-m-d', "{$data['mes']}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $this->syncPayrollForecast($company, $monthStart);

        return redirect()
            ->route('funcionarios.index', ['mes' => $data['mes']])
            ->with('status', 'Previsao de folha atualizada para os proximos meses.');

        $employees = $company->employees()
            ->where('is_active', true)
            ->where(function ($query) use ($monthEnd) {
                $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $monthEnd);
            })
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $monthStart);
            })
            ->orderBy('name')
            ->get();

        $fixedTotal = (float) $employees->sum('fixed_salary');
        $variableTotal = (float) $employees->sum('variable_salary');
        $dueDay = (int) min(max((int) ($employees->max('payment_day') ?: 5), 1), (int) $monthEnd->format('d'));
        $dueDate = $monthStart->copy()->day($dueDay);
        $created = 0;
        $updated = 0;

        if ($fixedTotal > 0) {
            [$wasCreated] = $this->upsertPayrollPayable(
                $company,
                $category,
                'Folha funcionarios fixa - '.$monthStart->format('m/Y'),
                $fixedTotal,
                $dueDate,
                'employee_fixed',
                'FUNC-FOLHA-FIXA-'.$monthStart->format('Y-m')
            );
            $wasCreated ? $created++ : $updated++;
        }

        if ($variableTotal > 0) {
            [$wasCreated] = $this->upsertPayrollPayable(
                $company,
                $category,
                'Folha funcionarios variavel - '.$monthStart->format('m/Y'),
                $variableTotal,
                $dueDate,
                'employee_variable',
                'FUNC-FOLHA-VARIAVEL-'.$monthStart->format('Y-m')
            );
            $wasCreated ? $created++ : $updated++;
        }

        return redirect()
            ->route('funcionarios.index', ['mes' => $data['mes']])
            ->with('status', "Previsao de folha atualizada para os proximos meses.");
    }

    public function storePayrollItem(Request $request, Employee $funcionario): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);
        [$data, $monthStart, $amount] = $this->payrollItemData($request, $company);

        $item = $funcionario->payrollItems()->create($data);
        if ($item->paid_outside && $amount > 0) {
            $this->createPaidOutsideWorkPayable($company, $funcionario, $item);
        }
        $this->syncPayrollForecast($company, $monthStart);

        return redirect()->route('funcionarios.recibo', ['funcionario' => $funcionario, 'mes' => Carbon::parse($data['reference_month'])->format('Y-m')])->with('status', 'Evento do recibo cadastrado.');
    }

    public function updatePayrollItem(Request $request, EmployeePayrollItem $item): RedirectResponse
    {
        $company = $this->company();
        abort_unless($item->employee->company_id === $company->id, 404);
        $employee = $item->employee;
        $oldMonthStart = $item->reference_month->copy()->startOfMonth();
        [$data, $monthStart, $amount] = $this->payrollItemData($request, $company);
        $outsidePayable = $company->payables()
            ->where('source', 'employee_extra_paid')
            ->where('document_number', 'FUNC-EXTRA-'.$item->id)
            ->first();

        $item->update($data);

        if (! $outsidePayable && $item->paid_outside && $amount > 0) {
            $this->createPaidOutsideWorkPayable($company, $employee, $item->refresh());
        } elseif ($outsidePayable && $outsidePayable->status === 'open' && (! $item->paid_outside || $amount <= 0)) {
            $outsidePayable->delete();
        }

        $this->syncPayrollForecast($company, $oldMonthStart);
        if (! $oldMonthStart->isSameMonth($monthStart)) {
            $this->syncPayrollForecast($company, $monthStart);
        }

        return redirect()->route('funcionarios.recibo', ['funcionario' => $employee, 'mes' => $monthStart->format('Y-m')])->with('status', 'Evento do recibo atualizado.');
    }

    public function deletePayrollItem(EmployeePayrollItem $item): RedirectResponse
    {
        $company = $this->company();
        abort_unless($item->employee->company_id === $company->id, 404);
        $month = $item->reference_month->format('Y-m');
        $monthStart = $item->reference_month->copy()->startOfMonth();
        $employee = $item->employee;
        if ($item->paid_outside) {
            $company->payables()
                ->where('source', 'employee_extra_paid')
                ->where('document_number', 'FUNC-EXTRA-'.$item->id)
                ->delete();
        }
        $item->delete();
        $this->syncPayrollForecast($company, $monthStart);

        return redirect()->route('funcionarios.recibo', ['funcionario' => $employee, 'mes' => $month])->with('status', 'Evento removido.');
    }

    public function storeAdvance(Request $request, Employee $funcionario): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);
        $data = $request->validate([
            'advance_date' => ['required', 'date'],
            'deduct_month' => ['nullable', 'date_format:Y-m'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:dinheiro,pix'],
        ]);
        $data['deduct_month'] = ! empty($data['deduct_month'])
            ? Carbon::createFromFormat('Y-m-d', $data['deduct_month'].'-01')->startOfMonth()->toDateString()
            : Carbon::parse($data['advance_date'])->addMonthNoOverflow()->startOfMonth()->toDateString();

        $funcionario->advances()->create($data);
        $this->syncPayrollForecast($company, Carbon::parse($data['deduct_month'])->startOfMonth());

        return redirect()->route('funcionarios.recibo', ['funcionario' => $funcionario, 'mes' => Carbon::parse($data['deduct_month'])->format('Y-m')])->with('status', 'Vale cadastrado.');
    }

    public function deleteAdvance(EmployeeAdvance $vale): RedirectResponse
    {
        $company = $this->company();
        abort_unless($vale->employee->company_id === $company->id, 404);
        $month = ($vale->deduct_month ?: $vale->advance_date)->format('Y-m');
        $monthStart = ($vale->deduct_month ?: $vale->advance_date)->copy()->startOfMonth();
        $employee = $vale->employee;
        $vale->delete();
        $this->syncPayrollForecast($company, $monthStart);

        return redirect()->route('funcionarios.recibo', ['funcionario' => $employee, 'mes' => $month])->with('status', 'Vale removido.');
    }

    public function markPayrollAsPaid(Request $request): RedirectResponse
    {
        $company = $this->company();
        $category = $this->employeeCategory($company);
        $data = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
        ]);

        $monthStart = Carbon::createFromFormat('Y-m-d', "{$data['mes']}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $updated = $company->payables()
            ->where('financial_category_id', $category->id)
            ->where('source', 'employee_recurring')
            ->where('document_number', 'FUNC-FOLHA-'.$monthStart->format('Y-m'))
            ->where('status', 'open')
            ->update([
                'status' => 'paid',
                'paid_at' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('funcionarios.index', ['mes' => $data['mes']])
            ->with('status', "Folha paga: {$updated} despesa(s) marcada(s) como pagas.");
    }

    private function validated(Request $request, PayrollCalculator $calculator): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'max:255'],
            'cbo_code' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['document'] = $data['document'] ? preg_replace('/\D+/', '', $data['document']) : null;
        $baseSalary = (float) $data['base_salary'];
        $calculated = $calculator->calculate($baseSalary);
        $data = array_merge($data, $calculated);
        $data['salary'] = $baseSalary;
        $data['fixed_salary'] = $baseSalary;
        $data['variable_salary'] = 0;

        return $data;
    }

    private function automaticPayrollItems(Employee $employee, PayrollCalculator $calculator): array
    {
        $position = $employee->company?->employeePositions()
            ->where('name', $employee->role)
            ->where('is_active', true)
            ->first();
        $baseSalary = $this->employeeBaseSalary($employee);
        $additionalAmount = $position && $position->additional_type && $position->additional_percent
            ? round(($baseSalary * (float) $position->additional_percent) / 100, 2)
            : 0.0;
        $calculated = $calculator->calculate($baseSalary + $additionalAmount);
        $items = [];

        if ($baseSalary > 0) {
            $items[] = (object) [
                'id' => null,
                'code' => '1',
                'description' => 'SALARIO BASE',
                'reference' => '220,00',
                'earning' => $baseSalary,
                'deduction' => 0,
                'automatic' => true,
            ];
        }

        if ($additionalAmount > 0 && $position) {
            $items[] = (object) [
                'id' => null,
                'code' => $position->additional_type === 'periculosidade' ? '30' : '31',
                'description' => strtoupper($position->additional_type),
                'reference' => number_format((float) $position->additional_percent, 2, ',', '.').'%',
                'earning' => $additionalAmount,
                'deduction' => 0,
                'automatic' => true,
            ];
        }

        if ($calculated['inss_discount'] > 0) {
            $items[] = (object) [
                'id' => null,
                'code' => '998',
                'description' => 'I.N.S.S.',
                'reference' => number_format($calculated['inss_discount'], 2, ',', '.'),
                'earning' => 0,
                'deduction' => $calculated['inss_discount'],
                'automatic' => true,
            ];
        }

        if ($calculated['irrf_discount'] > 0) {
            $items[] = (object) [
                'id' => null,
                'code' => '999',
                'description' => 'I.R.R.F.',
                'reference' => number_format($calculated['irrf_bracket'], 2, ',', '.').'%',
                'earning' => 0,
                'deduction' => $calculated['irrf_discount'],
                'automatic' => true,
            ];
        }

        return $items;
    }

    private function employeeBaseSalary(Employee $employee): float
    {
        foreach (['base_salary', 'fixed_salary', 'salary'] as $field) {
            $value = (float) $employee->{$field};
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function employeeCategory(Company $company): FinancialCategory
    {
        return $company->categories()->firstOrCreate(
            ['name' => 'Funcionarios', 'type' => 'expense'],
            ['is_default' => true, 'is_active' => true]
        );
    }

    private function upsertPayrollPayable(Company $company, FinancialCategory $category, string $description, float $amount, Carbon $dueDate, string $source, string $documentNumber): array
    {
        $payable = $company->payables()
            ->where('source', $source)
            ->where('document_number', $documentNumber)
            ->first();

        if ($payable) {
            if ($payable->status === 'open') {
                $payable->update([
                    'financial_category_id' => $category->id,
                    'description' => $description,
                    'amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                ]);
            }

            return [false, $payable];
        }

        return [true, $company->payables()->create([
            'financial_category_id' => $category->id,
            'description' => $description,
            'amount' => $amount,
            'due_date' => $dueDate->toDateString(),
            'status' => 'open',
            'source' => $source,
            'document_number' => $documentNumber,
            'notes' => 'Despesa consolidada da folha de funcionarios.',
        ])];
    }

    private function syncPayrollForecast(Company $company, ?Carbon $startMonth = null): void
    {
        $category = $this->employeeCategory($company);
        $calculator = app(PayrollCalculator::class);
        $start = ($startMonth ?: now())->copy()->startOfMonth();
        $end = $start->copy()->addMonths(11)->startOfMonth();

        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $employees = $company->employees()
                ->where('is_active', true)
                ->where(function ($query) use ($month) {
                    $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $month->copy()->endOfMonth());
                })
                ->where(function ($query) use ($month) {
                    $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month);
                })
                ->get();

            $amount = $this->monthlyPayrollAmount($company, $month, $calculator, $employees);
            $paymentDay = (int) min(max((int) ($employees->max('payment_day') ?: 5), 1), 28);
            $dueDate = $month->copy()->addMonthNoOverflow()->day($paymentDay);

            if ($amount <= 0) {
                $company->payables()
                    ->where('source', 'employee_recurring')
                    ->where('document_number', 'FUNC-FOLHA-'.$month->format('Y-m'))
                    ->where('status', 'open')
                    ->delete();
                continue;
            }

            $this->upsertPayrollPayable(
                $company,
                $category,
                'Folha funcionarios - '.$month->format('m/Y'),
                $amount,
                $dueDate,
                'employee_recurring',
                'FUNC-FOLHA-'.$month->format('Y-m')
            );
        }
    }

    private function createPaidOutsideWorkPayable(Company $company, Employee $employee, EmployeePayrollItem $item): void
    {
        $category = $this->employeeCategory($company);
        $workedDate = $item->worked_date ?: $item->reference_month;
        $label = $item->event_type === 'holiday_work' ? 'Trabalho feriado' : 'Trabalho domingo';

        $company->payables()->updateOrCreate([
            'source' => 'employee_extra_paid',
            'document_number' => 'FUNC-EXTRA-'.$item->id,
        ], [
            'financial_category_id' => $category->id,
            'description' => $label.' - '.$employee->name.' - '.$workedDate->format('d/m/Y'),
            'amount' => (float) $item->earning,
            'due_date' => $workedDate->toDateString(),
            'status' => 'paid',
            'paid_at' => $item->paid_at ?: now()->toDateString(),
            'account_type' => 'boleto',
            'notes' => 'Movimento de funcionario pago por fora da folha.',
        ]);
    }

    private function monthlyPayrollAmount(Company $company, Carbon $month, PayrollCalculator $calculator, mixed $employees = null): float
    {
        $month = $month->copy()->startOfMonth();
        $employees = $employees ?: $company->employees()
            ->where('is_active', true)
            ->where(function ($query) use ($month) {
                $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $month->copy()->endOfMonth());
            })
            ->where(function ($query) use ($month) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month);
            })
            ->get();
        $employeeIds = $employees->pluck('id');
        $automaticTotal = (float) $employees->sum(function (Employee $employee) use ($calculator) {
            return collect($this->automaticPayrollItems($employee, $calculator))->sum(fn ($item) => (float) $item->earning - (float) $item->deduction);
        });
        $eventTotal = (float) EmployeePayrollItem::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('reference_month', $month->toDateString())
            ->where('paid_outside', false)
            ->sum(DB::raw('earning - deduction'));
        $advanceTotal = (float) EmployeeAdvance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('deduct_month', $month->toDateString())
            ->sum('amount');

        return max(0, $automaticTotal + $eventTotal - $advanceTotal);
    }

    private function employeeMonthlyNetAmount(Employee $employee, Carbon $month, PayrollCalculator $calculator): float
    {
        $month = $month->copy()->startOfMonth();
        $automaticTotal = collect($this->automaticPayrollItems($employee, $calculator))
            ->sum(fn ($item) => (float) $item->earning - (float) $item->deduction);
        $eventTotal = (float) $employee->payrollItems()
            ->whereDate('reference_month', $month->toDateString())
            ->where('paid_outside', false)
            ->sum(DB::raw('earning - deduction'));
        $advanceTotal = (float) $employee->advances()
            ->whereDate('deduct_month', $month->toDateString())
            ->sum('amount');

        return max(0, $automaticTotal + $eventTotal - $advanceTotal);
    }

    private function payrollItemData(Request $request, Company $company): array
    {
        $data = $request->validate([
            'reference_month' => ['required', 'date_format:Y-m'],
            'movement_type_id' => ['nullable', 'integer'],
            'event_type' => ['nullable', 'string', 'max:80'],
            'worked_date' => ['nullable', 'date'],
            'paid_outside' => ['nullable', 'boolean'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'earning' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
        ]);
        $monthStart = Carbon::createFromFormat('Y-m-d', $data['reference_month'].'-01')->startOfMonth();
        $type = $this->movementTypeForRequest($company, $data);
        $data['reference_month'] = $monthStart->toDateString();
        $data['event_type'] = $type->code;
        $data['reference'] = null;
        $data['code'] = ($data['code'] ?? null) ?: $type->code;
        $isDeduction = $type->kind === 'debit';
        $amount = $isDeduction ? (float) ($data['deduction'] ?: 0) : (float) ($data['earning'] ?: 0);
        $data['earning'] = $isDeduction ? 0 : $amount;
        $data['deduction'] = $isDeduction ? $amount : 0;
        $data['paid_outside'] = $type->allows_paid_outside && $request->boolean('paid_outside');
        $data['paid_at'] = $data['paid_outside'] ? now()->toDateString() : null;

        if (! $type->requires_worked_date) {
            $data['worked_date'] = null;
            $data['paid_outside'] = false;
            $data['paid_at'] = null;
        }

        return [$data, $monthStart, $amount];
    }

    private function movementTypeForRequest(Company $company, array $data): EmployeeMovementType
    {
        $query = $this->ensureDefaultMovementTypes($company);
        if (! empty($data['movement_type_id'])) {
            $type = $query->where('id', $data['movement_type_id'])->first();
            if ($type) {
                return $type;
            }
        }

        return $query->where('code', $data['event_type'] ?? 'earning')->firstOrFail();
    }

    private function ensureDefaultMovementTypes(Company $company)
    {
        foreach ($this->defaultMovementTypes() as $type) {
            $company->employeeMovementTypes()->firstOrCreate(['code' => $type['code']], $type);
        }

        return $company->employeeMovementTypes();
    }

    private function defaultMovementTypes(): array
    {
        return [
            ['code' => 'vale', 'name' => 'Vale / adiantamento', 'kind' => 'debit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'bonus', 'name' => 'Bonificacao', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'thirteenth', 'name' => '13 salario', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'vacation', 'name' => 'Ferias', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
            ['code' => 'sunday_work', 'name' => 'Trabalho domingo', 'kind' => 'credit', 'requires_worked_date' => true, 'allows_paid_outside' => true, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'holiday_work', 'name' => 'Trabalho feriado', 'kind' => 'credit', 'requires_worked_date' => true, 'allows_paid_outside' => true, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'discount', 'name' => 'Desconto / imposto', 'kind' => 'debit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => false, 'is_active' => true],
            ['code' => 'earning', 'name' => 'Outro acrescimo', 'kind' => 'credit', 'requires_worked_date' => false, 'allows_paid_outside' => false, 'is_taxable' => true, 'is_active' => true],
        ];
    }

    private function validMonth(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
