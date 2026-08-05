<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        $categories = $company->categories()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('settings.categories', [
            'company' => $company,
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request, $company);
        $data['is_default'] = false;
        $data['is_active'] = true;

        $company->categories()->create($data);

        return redirect()->route('configuracoes.categorias.index')->with('status', 'Categoria cadastrada.');
    }

    public function update(Request $request, FinancialCategory $categoria): RedirectResponse
    {
        $company = $this->company();
        abort_unless($categoria->company_id === $company->id, 404);

        $data = $this->validated($request, $company, $categoria);
        $data['is_active'] = $request->boolean('is_active');
        $categoria->update($data);

        return redirect()->route('configuracoes.categorias.index')->with('status', 'Categoria atualizada.');
    }

    public function destroy(FinancialCategory $categoria): RedirectResponse
    {
        $company = $this->company();
        abort_unless($categoria->company_id === $company->id, 404);
        $categoria->update(['is_active' => false]);

        return redirect()->route('configuracoes.categorias.index')->with('status', 'Categoria inativada.');
    }

    private function validated(Request $request, Company $company, ?FinancialCategory $category = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('financial_categories', 'name')
                    ->where('company_id', $company->id)
                    ->where('type', $request->input('type', 'expense'))
                    ->ignore($category?->id),
            ],
            'type' => ['required', 'in:expense,revenue'],
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
