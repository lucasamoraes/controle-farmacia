<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\EmployeeDepartment;
use App\Models\EmployeePosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeReferenceController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        return view('employee-references.index', [
            'company' => $company,
            'positions' => $company->employeePositions()->orderByDesc('is_active')->orderBy('name')->get(),
            'departments' => $company->employeeDepartments()->orderByDesc('is_active')->orderBy('name')->get(),
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

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
