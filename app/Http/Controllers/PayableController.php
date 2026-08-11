<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayableController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        [$dateStart, $dateEnd] = $this->dateRange($request);
        $search = trim((string) $request->query('busca', ''));
        $status = $request->query('status', 'open');
        $period = $request->query('periodo', 'month');

        if (($dateStart || $dateEnd) && ($request->query('periodo') === null || $period === 'custom')) {
            $period = 'custom';
        }

        $payablesQuery = $company->payables()
            ->with(['supplier', 'category'])
            ->when($search !== '', fn ($query) => $this->applySearch($query, $search))
            ->when(in_array($status, ['open', 'paid', 'cancelled', 'overdue'], true), function ($query) use ($status) {
                if ($status === 'overdue') {
                    $query->where('status', 'open')->whereDate('due_date', '<', Carbon::today());
                    return;
                }

                $query->where('status', $status);
            })
            ->when($dateStart, fn ($query) => $query->whereDate('due_date', '>=', $dateStart))
            ->when($dateEnd, fn ($query) => $query->whereDate('due_date', '<=', $dateEnd));

        $filteredTotal = (clone $payablesQuery)->where('status', '!=', 'cancelled')->sum('amount');
        $payables = $payablesQuery
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'paid' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->paginate(12)
            ->withQueryString();

        return view('payables.index', [
            'company' => $company,
            'payables' => $payables,
            'search' => $search,
            'statusFilter' => $status,
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'filteredTotal' => $filteredTotal,
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
        $data['account_type'] = $data['account_type'] ?? 'boleto';

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
            'account_type' => ['nullable', 'in:boleto,credit_card'],
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

    private function applySearch($query, string $search): void
    {
        $query->where(function ($inner) use ($search) {
            $inner->where('description', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")
                ->orWhere('digitable_line', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($supplier) use ($search) {
                    $supplier->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                })
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
        });
    }

    private function dateRange(Request $request): array
    {
        $period = $request->query('periodo');
        $today = Carbon::today();
        $hasCustomDates = $request->query('inicio') || $request->query('fim');
        $useCustomDates = $period === 'custom' || ($period === null && $hasCustomDates);
        $customStart = $useCustomDates ? $this->validDate($request->query('inicio')) : null;
        $customEnd = $useCustomDates ? $this->validDate($request->query('fim')) : null;

        if ($customStart || $customEnd) {
            return [$customStart, $customEnd];
        }

        return match ($period) {
            '7' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            '30' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            'next7' => [$today->toDateString(), $today->copy()->addDays(7)->toDateString()],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
