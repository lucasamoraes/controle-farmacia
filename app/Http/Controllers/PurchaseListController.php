<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseList;
use App\Models\PurchaseListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PurchaseListController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $status = $request->query('status', '');
        $search = trim((string) $request->query('busca', ''));

        return view('purchase-lists.index', [
            'company' => $company,
            'status' => $status,
            'search' => $search,
            'lists' => $company->purchaseLists()
                ->withCount('items')
                ->with('creator')
                ->when(in_array($status, ['open', 'quoting', 'finalized'], true), fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'quoting' THEN 2 ELSE 3 END")
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('purchase-lists.create', [
            'company' => $this->company(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        abort_unless(Auth::user()->canWritePurchaseList($company), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $list = $company->purchaseLists()->create([
            'created_by' => Auth::id(),
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'status' => 'open',
        ]);

        return redirect()->route('listas-compras.show', $list)->with('status', 'Lista criada. Agora adicione os produtos em falta.');
    }

    public function show(Request $request, PurchaseList $lista): View
    {
        $this->abortUnlessCompanyList($lista);
        $company = $this->company();

        return view('purchase-lists.show', [
            'company' => $company,
            'list' => $lista->load(['items.product', 'quotation']),
            'products' => $company->products()
                ->where('is_active', true)
                ->orderBy('description')
                ->limit(2500)
                ->get(),
            'productClasses' => $company->productClasses()->where('is_active', true)->orderBy('name')->get(),
            'canEditItems' => $lista->status === 'open' && Auth::user()->canWritePurchaseList($company),
            'canManageQuotation' => Auth::user()->canWriteFinance($company),
        ]);
    }

    public function addItem(Request $request, PurchaseList $lista): RedirectResponse
    {
        $this->abortUnlessCompanyList($lista);
        $company = $this->company();
        abort_unless(Auth::user()->canWritePurchaseList($company), 403);
        abort_unless($lista->status === 'open', 403);

        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        $product = null;
        if (! empty($data['product_id'])) {
            $product = Product::where('company_id', $company->id)->findOrFail($data['product_id']);
        }

        if (! $product) {
            return back()->withErrors(['product_id' => 'Selecione um produto cadastrado ou cadastre um novo produto.'])->withInput();
        }

        $lista->items()->create([
            'product_id' => $product->id,
            'description' => $product->description,
            'ean' => null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('listas-compras.show', $lista)->with('status', 'Produto adicionado a lista.');
    }

    public function storeProductItem(Request $request, PurchaseList $lista): RedirectResponse
    {
        $this->abortUnlessCompanyList($lista);
        $company = $this->company();
        abort_unless(Auth::user()->canWritePurchaseList($company), 403);
        abort_unless($lista->status === 'open', 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'last_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = $company->products()->create([
            'description' => $data['description'],
            'class' => $data['class'] ?? null,
            'last_purchase_price' => $data['last_purchase_price'] ?? 0,
            'is_active' => true,
        ]);

        $lista->items()->create([
            'product_id' => $product->id,
            'description' => $product->description,
            'ean' => null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('listas-compras.show', $lista)->with('status', 'Produto cadastrado e adicionado a lista.');
    }

    public function updateStatus(Request $request, PurchaseList $lista): RedirectResponse
    {
        $this->abortUnlessCompanyList($lista);
        $data = $request->validate([
            'status' => ['required', 'in:open,quoting,finalized'],
        ]);

        $lista->update([
            'status' => $data['status'],
            'started_quotation_at' => $data['status'] === 'quoting' ? ($lista->started_quotation_at ?: now()) : $lista->started_quotation_at,
            'finalized_at' => $data['status'] === 'finalized' ? now() : null,
        ]);

        return redirect()->route('listas-compras.show', $lista)->with('status', 'Status da lista atualizado.');
    }

    public function destroy(PurchaseList $lista): RedirectResponse
    {
        $this->abortUnlessCompanyList($lista);
        abort_unless(Auth::user()->canWriteFinance($this->company()), 403);
        $lista->delete();

        return redirect()->route('listas-compras.index')->with('status', 'Lista excluida.');
    }

    public function removeItem(PurchaseListItem $item): RedirectResponse
    {
        $list = $item->purchaseList;
        $this->abortUnlessCompanyList($list);
        abort_unless($list->status === 'open' && Auth::user()->canWritePurchaseList($this->company()), 403);
        $item->delete();

        return redirect()->route('listas-compras.show', $list)->with('status', 'Item removido.');
    }

    private function abortUnlessCompanyList(PurchaseList $list): void
    {
        abort_unless($list->company_id === $this->company()->id, 404);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
