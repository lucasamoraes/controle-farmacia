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

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
