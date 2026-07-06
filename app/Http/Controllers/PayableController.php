<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayableController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        return view('payables.index', [
            'company' => $company,
            'payables' => $company->payables()->with(['supplier', 'category'])->orderBy('due_date')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('payables.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request);
        $data['status'] = $data['status'] ?? 'open';
        $data['source'] = 'manual';

        $company->payables()->create($data);

        return redirect()->route('contas-a-pagar.index')->with('status', 'Conta a pagar criada.');
    }

    public function edit(Payable $contas_a_pagar): View
    {
        $company = $this->company();
        abort_unless($contas_a_pagar->company_id === $company->id, 404);

        return view('payables.edit', $this->formData() + ['payable' => $contas_a_pagar]);
    }

    public function update(Request $request, Payable $contas_a_pagar): RedirectResponse
    {
        $company = $this->company();
        abort_unless($contas_a_pagar->company_id === $company->id, 404);

        $data = $this->validated($request);
        if (($data['status'] ?? null) !== 'paid') {
            $data['paid_at'] = null;
        } elseif (empty($data['paid_at'])) {
            $data['paid_at'] = now()->toDateString();
        }

        $contas_a_pagar->update($data);

        return redirect()->route('contas-a-pagar.index')->with('status', 'Conta a pagar atualizada.');
    }

    public function destroy(Payable $contas_a_pagar): RedirectResponse
    {
        $company = $this->company();
        abort_unless($contas_a_pagar->company_id === $company->id, 404);

        $contas_a_pagar->update(['status' => 'cancelled']);

        return redirect()->route('contas-a-pagar.index')->with('status', 'Conta cancelada.');
    }

    public function delete(Payable $contas_a_pagar): RedirectResponse
    {
        $company = $this->company();
        abort_unless($contas_a_pagar->company_id === $company->id, 404);

        $contas_a_pagar->delete();

        return redirect()->route('contas-a-pagar.index')->with('status', 'Conta excluida.');
    }

    public function markAsPaid(Payable $contas_a_pagar): RedirectResponse
    {
        $company = $this->company();
        abort_unless($contas_a_pagar->company_id === $company->id, 404);

        $contas_a_pagar->update([
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
        ]);

        return redirect()->route('contas-a-pagar.index')->with('status', 'Conta marcada como paga.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'financial_category_id' => ['nullable', 'exists:financial_categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:open,paid,cancelled'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'digitable_line' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function formData(): array
    {
        $company = $this->company();

        return [
            'company' => $company,
            'suppliers' => $company->suppliers()->where('is_active', true)->orderBy('name')->get(),
            'categories' => $company->categories()->where('type', 'expense')->orderBy('name')->get(),
        ];
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
