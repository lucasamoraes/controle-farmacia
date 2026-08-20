<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProductClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductClassController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        return view('product-classes.index', [
            'company' => $company,
            'search' => $search,
            'classes' => $company->productClasses()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request);

        $company->productClasses()->updateOrCreate(
            ['name' => mb_strtoupper($data['name'])],
            ['is_active' => true]
        );

        return redirect()->route('configuracoes.classes-produtos.index')->with('status', 'Classe cadastrada.');
    }

    public function update(Request $request, ProductClass $classe): RedirectResponse
    {
        $this->abortUnlessCompanyClass($classe);
        $data = $this->validated($request);
        $classe->update(['name' => mb_strtoupper($data['name'])]);

        return redirect()->route('configuracoes.classes-produtos.index')->with('status', 'Classe atualizada.');
    }

    public function destroy(ProductClass $classe): RedirectResponse
    {
        $this->abortUnlessCompanyClass($classe);
        $classe->update(['is_active' => ! $classe->is_active]);

        return redirect()->route('configuracoes.classes-produtos.index')->with('status', 'Status da classe atualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }

    private function abortUnlessCompanyClass(ProductClass $class): void
    {
        abort_unless($class->company_id === $this->company()->id, 404);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
