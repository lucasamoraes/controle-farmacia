<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        return view('suppliers.index', [
            'company' => $company,
            'suppliers' => $company->suppliers()->with('category')->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        $company = $this->company();

        return view('suppliers.create', [
            'company' => $company,
            'categories' => $company->categories()->where('type', 'expense')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request);

        $company->suppliers()->create($data);

        return redirect()->route('fornecedores.index')->with('status', 'Fornecedor cadastrado.');
    }

    public function edit(Supplier $fornecedore): View
    {
        $company = $this->company();
        abort_unless($fornecedore->company_id === $company->id, 404);

        return view('suppliers.edit', [
            'company' => $company,
            'supplier' => $fornecedore,
            'categories' => $company->categories()->where('type', 'expense')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Supplier $fornecedore): RedirectResponse
    {
        $company = $this->company();
        abort_unless($fornecedore->company_id === $company->id, 404);

        $fornecedore->update($this->validated($request));

        return redirect()->route('fornecedores.index')->with('status', 'Fornecedor atualizado.');
    }

    public function destroy(Supplier $fornecedore): RedirectResponse
    {
        $company = $this->company();
        abort_unless($fornecedore->company_id === $company->id, 404);

        $fornecedore->update(['is_active' => false]);

        return redirect()->route('fornecedores.index')->with('status', 'Fornecedor inativado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'financial_category_id' => ['nullable', 'exists:financial_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'legal_status' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip_code' => ['nullable', 'string', 'max:12'],
            'main_activity' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}

