<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeMovementType;
use App\Models\EmployeePosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeReferenceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        return view('employee-references.index', [
            'company' => $company,
            'positions' => $company->employeePositions()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('cbo_code', 'like', "%{$search}%"))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'departments' => $company->employeeDepartments()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'movementTypes' => $this->ensureDefaultMovementTypes($company)
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'search' => $search,
        ]);
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cbo_code' => ['nullable', 'string', 'max:20'],
            'additional_type' => ['nullable', 'in:insalubridade,periculosidade'],
            'additional_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->company()->employeePositions()->create($data);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Cargo cadastrado.');
    }

    public function destroyPosition(EmployeePosition $cargo): RedirectResponse
    {
        $company = $this->company();
        abort_unless($cargo->company_id === $company->id, 404);
        $cargo->update(['is_active' => false]);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Cargo inativado.');
    }

    public function updatePosition(Request $request, EmployeePosition $cargo): RedirectResponse
    {
        $company = $this->company();
        abort_unless($cargo->company_id === $company->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cbo_code' => ['nullable', 'string', 'max:20'],
            'additional_type' => ['nullable', 'in:insalubridade,periculosidade'],
            'additional_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $cargo->update($data + ['is_active' => $request->boolean('is_active', $cargo->is_active)]);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Cargo atualizado.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->company()->employeeDepartments()->create($data);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Departamento cadastrado.');
    }

    public function destroyDepartment(EmployeeDepartment $departamento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($departamento->company_id === $company->id, 404);
        $departamento->update(['is_active' => false]);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Departamento inativado.');
    }

    public function updateDepartment(Request $request, EmployeeDepartment $departamento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($departamento->company_id === $company->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $departamento->update($data + ['is_active' => $request->boolean('is_active', $departamento->is_active)]);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Departamento atualizado.');
    }

    public function storeMovementType(Request $request): RedirectResponse
    {
        $data = $this->movementTypeData($request);
        $data['code'] = $this->movementCode($data['name']);
        $this->company()->employeeMovementTypes()->create($data);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Tipo de movimento cadastrado.');
    }

    public function updateMovementType(Request $request, EmployeeMovementType $movimento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($movimento->company_id === $company->id, 404);
        $data = $this->movementTypeData($request);
        $data['is_active'] = $request->boolean('is_active', $movimento->is_active);
        $movimento->update($data);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Tipo de movimento atualizado.');
    }

    public function destroyMovementType(EmployeeMovementType $movimento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($movimento->company_id === $company->id, 404);
        $movimento->update(['is_active' => false]);

        return redirect()->route('configuracoes.funcionarios.index')->with('status', 'Tipo de movimento inativado.');
    }

    private function movementTypeData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:credit,debit'],
            'requires_worked_date' => ['nullable', 'boolean'],
            'allows_paid_outside' => ['nullable', 'boolean'],
            'is_taxable' => ['nullable', 'boolean'],
        ]);

        return $data + [
            'requires_worked_date' => $request->boolean('requires_worked_date'),
            'allows_paid_outside' => $request->boolean('allows_paid_outside'),
            'is_taxable' => $request->boolean('is_taxable'),
        ];
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

    private function movementCode(string $name): string
    {
        $code = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '_'));

        return $code ?: 'movimento';
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
