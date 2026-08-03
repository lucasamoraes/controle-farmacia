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
            ->with('status', "Folha gerada: {$created} nova(s), {$updated} atualizada(s).");
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
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
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
