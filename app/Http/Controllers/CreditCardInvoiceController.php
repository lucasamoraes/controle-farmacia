<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditCardInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditCardInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        $invoices = $company->creditCardInvoices()
            ->with(['items.category', 'payable'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('card_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            })
            ->orderByDesc('reference_month')
            ->orderByDesc('due_date')
            ->paginate(12)
            ->withQueryString();

        return view('credit-card-invoices.index', [
            'company' => $company,
            'invoices' => $invoices,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('credit-card-invoices.form', $this->formData() + [
            'invoice' => new CreditCardInvoice([
                'reference_month' => now()->startOfMonth(),
                'due_date' => now()->endOfMonth(),
                'status' => 'open',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request);

        DB::transaction(function () use ($company, $data) {
            $items = $this->validItems($data['items']);
            $total = collect($items)->sum('amount');
            $referenceMonth = Carbon::createFromFormat('Y-m-d', $data['reference_month'].'-01')->startOfMonth()->toDateString();

            $payable = $company->payables()->create([
                'financial_category_id' => $this->cardCategory($company)->id,
                'description' => 'Fatura cartao - '.$data['card_name'].' - '.Carbon::parse($referenceMonth)->format('m/Y'),
                'amount' => $total,
                'due_date' => $data['due_date'],
                'status' => $data['status'],
                'paid_at' => $data['status'] === 'paid' ? ($data['paid_at'] ?? now()->toDateString()) : null,
                'source' => 'credit_card_invoice',
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice = $company->creditCardInvoices()->create([
                'payable_id' => $payable->id,
                'card_name' => $data['card_name'],
                'reference_month' => $referenceMonth,
                'due_date' => $data['due_date'],
                'total_amount' => $total,
                'status' => $data['status'],
                'paid_at' => $data['status'] === 'paid' ? ($data['paid_at'] ?? now()->toDateString()) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->createMany($items);
        });

        return redirect()->route('faturas-cartao.index')->with('status', 'Fatura cadastrada.');
    }

    public function edit(CreditCardInvoice $faturas_cartao): View
    {
        $company = $this->company();
        abort_unless($faturas_cartao->company_id === $company->id, 404);

        return view('credit-card-invoices.form', $this->formData() + [
            'invoice' => $faturas_cartao->load('items'),
        ]);
    }

    public function update(Request $request, CreditCardInvoice $faturas_cartao): RedirectResponse
    {
        $company = $this->company();
        abort_unless($faturas_cartao->company_id === $company->id, 404);
        $data = $this->validated($request);

        DB::transaction(function () use ($company, $faturas_cartao, $data) {
            $items = $this->validItems($data['items']);
            $total = collect($items)->sum('amount');
            $referenceMonth = Carbon::createFromFormat('Y-m-d', $data['reference_month'].'-01')->startOfMonth()->toDateString();
            $paidAt = $data['status'] === 'paid' ? ($data['paid_at'] ?? now()->toDateString()) : null;

            $faturas_cartao->update([
                'card_name' => $data['card_name'],
                'reference_month' => $referenceMonth,
                'due_date' => $data['due_date'],
                'total_amount' => $total,
                'status' => $data['status'],
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
            ]);
            $faturas_cartao->items()->delete();
            $faturas_cartao->items()->createMany($items);

            $payable = $faturas_cartao->payable ?: $company->payables()->make(['source' => 'credit_card_invoice']);
            $payable->fill([
                'company_id' => $company->id,
                'financial_category_id' => $this->cardCategory($company)->id,
                'description' => 'Fatura cartao - '.$data['card_name'].' - '.Carbon::parse($referenceMonth)->format('m/Y'),
                'amount' => $total,
                'due_date' => $data['due_date'],
                'status' => $data['status'],
                'paid_at' => $paidAt,
                'source' => 'credit_card_invoice',
                'notes' => $data['notes'] ?? null,
            ]);
            $payable->save();

            if (! $faturas_cartao->payable_id) {
                $faturas_cartao->update(['payable_id' => $payable->id]);
            }
        });

        return redirect()->route('faturas-cartao.index')->with('status', 'Fatura atualizada.');
    }

    public function destroy(CreditCardInvoice $faturas_cartao): RedirectResponse
    {
        $company = $this->company();
        abort_unless($faturas_cartao->company_id === $company->id, 404);

        DB::transaction(function () use ($faturas_cartao) {
            $faturas_cartao->payable?->delete();
            $faturas_cartao->delete();
        });

        return redirect()->route('faturas-cartao.index')->with('status', 'Fatura excluida.');
    }

    public function markAsPaid(CreditCardInvoice $faturas_cartao): RedirectResponse
    {
        $company = $this->company();
        abort_unless($faturas_cartao->company_id === $company->id, 404);

        $paidAt = now()->toDateString();
        $faturas_cartao->update(['status' => 'paid', 'paid_at' => $paidAt]);
        $faturas_cartao->payable?->update(['status' => 'paid', 'paid_at' => $paidAt]);

        return redirect()->route('faturas-cartao.index')->with('status', 'Fatura marcada como paga.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'card_name' => ['required', 'string', 'max:255'],
            'reference_month' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:open,paid,cancelled'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.financial_category_id' => ['nullable', 'exists:financial_categories,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
    }

    private function validItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => trim((string) ($item['description'] ?? '')) !== '' && (float) ($item['amount'] ?? 0) > 0)
            ->map(fn ($item) => [
                'financial_category_id' => $item['financial_category_id'] ?: null,
                'description' => trim((string) $item['description']),
                'amount' => round((float) $item['amount'], 2),
            ])
            ->values()
            ->all();
    }

    private function formData(): array
    {
        $company = $this->company();

        return [
            'company' => $company,
            'categories' => $company->categories()->where('type', 'expense')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function cardCategory(Company $company)
    {
        return $company->categories()->firstOrCreate(
            ['name' => 'Cartao de credito', 'type' => 'expense'],
            ['is_default' => true, 'is_active' => true]
        );
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
