<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\FinancialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $category = $this->employeeCategory($company);
        $month = $this->validMonth($request->query('mes')) ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $search = trim((string) $request->query('busca', ''));
        $status = $request->query('status', 'active');

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

        return view('employees.index', [
            'company' => $company,
            'employees' => $employees,
            'monthExpenses' => $monthExpenses,
            'month' => $month,
            'search' => $search,
            'statusFilter' => $status,
            'activeFixedPayrollTotal' => $company->employees()->where('is_active', true)->sum('fixed_salary'),
            'activeVariablePayrollTotal' => $company->employees()->where('is_active', true)->sum('variable_salary'),
            'activePayrollTotal' => $company->employees()->where('is_active', true)->sum('fixed_salary') + $company->employees()->where('is_active', true)->sum('variable_salary'),
            'monthExpenseTotal' => $monthExpenses->where('status', '!=', 'cancelled')->sum('amount'),
            'monthOpenTotal' => $monthExpenses->where('status', 'open')->sum('amount'),
            'monthPaidTotal' => $monthExpenses->where('status', 'paid')->sum('amount'),
        ]);
    }

    public function create(): View
    {
        return view('employees.form', [
            'company' => $this->company(),
            'employee' => new Employee(['payment_day' => 5, 'is_active' => true, 'fixed_salary' => 0, 'variable_salary' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $company->employees()->create($this->validated($request));

        return redirect()->route('funcionarios.index')->with('status', 'Funcionario cadastrado.');
    }

    public function edit(Employee $funcionario): View
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        return view('employees.form', [
            'company' => $company,
            'employee' => $funcionario,
        ]);
    }

    public function update(Request $request, Employee $funcionario): RedirectResponse
    {
        $company = $this->company();
        abort_unless($funcionario->company_id === $company->id, 404);

        $funcionario->update($this->validated($request));

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
        $created = 0;
        $skipped = 0;

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

        foreach ($employees as $employee) {
            $fixedDocument = sprintf('FUNC-FIXO-%d-%s', $employee->id, $monthStart->format('Y-m'));
            $variableDocument = sprintf('FUNC-VARIAVEL-%d-%s', $employee->id, $monthStart->format('Y-m'));

            $fixedExists = $company->payables()
                ->whereIn('source', ['employee_recurring', 'employee_fixed'])
                ->whereIn('document_number', [$fixedDocument, sprintf('FUNC-%d-%s', $employee->id, $monthStart->format('Y-m'))])
                ->exists();

            if ($fixedExists) {
                $skipped++;
            } elseif ((float) $employee->fixed_salary > 0) {
                $dueDay = min(max((int) $employee->payment_day, 1), (int) $monthEnd->format('d'));
                $dueDate = $monthStart->copy()->day($dueDay);

                $company->payables()->create([
                    'financial_category_id' => $category->id,
                    'description' => 'Salario fixo - '.$employee->name,
                    'amount' => $employee->fixed_salary,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'open',
                    'source' => 'employee_fixed',
                    'document_number' => $fixedDocument,
                    'notes' => trim((string) $employee->role) !== '' ? 'Cargo: '.$employee->role : null,
                ]);
                $created++;
            }

            if ((float) $employee->variable_salary <= 0) {
                continue;
            }

            $variableExists = $company->payables()
                ->where('source', 'employee_variable')
                ->where('document_number', $variableDocument)
                ->exists();

            if ($variableExists) {
                $skipped++;
                continue;
            }

            $dueDay = min(max((int) $employee->payment_day, 1), (int) $monthEnd->format('d'));
            $dueDate = $monthStart->copy()->day($dueDay);

            $company->payables()->create([
                'financial_category_id' => $category->id,
                'description' => 'Salario variavel - '.$employee->name,
                'amount' => $employee->variable_salary,
                'due_date' => $dueDate->toDateString(),
                'status' => 'open',
                'source' => 'employee_variable',
                'document_number' => $variableDocument,
                'notes' => trim((string) $employee->role) !== '' ? 'Cargo: '.$employee->role : null,
            ]);
            $created++;
        }

        return redirect()
            ->route('funcionarios.index', ['mes' => $data['mes']])
            ->with('status', "Despesas geradas: {$created}. Ja existentes: {$skipped}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'max:255'],
            'fixed_salary' => ['required', 'numeric', 'min:0'],
            'variable_salary' => ['nullable', 'numeric', 'min:0'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['document'] = $data['document'] ? preg_replace('/\D+/', '', $data['document']) : null;
        $data['variable_salary'] = $data['variable_salary'] ?? 0;
        $data['salary'] = (float) $data['fixed_salary'] + (float) $data['variable_salary'];

        return $data;
    }

    private function employeeCategory(Company $company): FinancialCategory
    {
        return $company->categories()->firstOrCreate(
            ['name' => 'Funcionarios', 'type' => 'expense'],
            ['is_default' => true, 'is_active' => true]
        );
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
