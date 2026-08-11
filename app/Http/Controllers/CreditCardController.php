<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CreditCardController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        return view('credit-cards.index', [
            'company' => $company,
            'cards' => $company->creditCards()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->company()->creditCards()->create($this->validated($request));

        return redirect()->route('configuracoes.cartoes.index')->with('status', 'Cartao cadastrado.');
    }

    public function update(Request $request, CreditCard $cartao): RedirectResponse
    {
        $company = $this->company();
        abort_unless($cartao->company_id === $company->id, 404);
        $cartao->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('configuracoes.cartoes.index')->with('status', 'Cartao atualizado.');
    }

    public function destroy(CreditCard $cartao): RedirectResponse
    {
        $company = $this->company();
        abort_unless($cartao->company_id === $company->id, 404);
        $cartao->update(['is_active' => false]);

        return redirect()->route('configuracoes.cartoes.index')->with('status', 'Cartao inativado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'closing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
