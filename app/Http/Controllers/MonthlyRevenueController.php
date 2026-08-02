<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MonthlyRevenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MonthlyRevenueController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));

        $revenues = $company->monthlyRevenues()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('notes', 'like', "%{$search}%")
                        ->orWhere('important_info', 'like', "%{$search}%");

                    if (preg_match('/^\d{4}-\d{2}$/', $search)) {
                        $inner->orWhereDate('reference_month', Carbon::createFromFormat('Y-m-d', $search . '-01')->startOfMonth());
                    }

                    if (preg_match('/^(\d{2})\/(\d{4})$/', $search, $matches)) {
                        $inner->orWhereDate('reference_month', Carbon::createFromFormat('Y-m-d', $matches[2] . '-' . $matches[1] . '-01')->startOfMonth());
                    }
                });
            })
            ->orderByDesc('reference_month')
            ->paginate(12)
            ->withQueryString();

        return view('monthly-revenues.index', [
            'company' => $company,
            'revenues' => $revenues,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $company = $this->company();
        $month = $this->monthFromRequest($request);
        $revenue = $company->monthlyRevenues()->whereDate('reference_month', $month)->first();

        return view('monthly-revenues.form', [
            'company' => $company,
            'revenue' => $revenue,
            'referenceMonth' => $month,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->normalizeRevenueData($this->validated($request));
        $data['reference_month'] = Carbon::createFromFormat('Y-m-d', $data['reference_month'] . '-01')->startOfMonth()->toDateString();
        $data['average_ticket'] = $this->averageTicket($data);

        $company->monthlyRevenues()->updateOrCreate(
            ['reference_month' => $data['reference_month']],
            $data
        );

        return redirect()->route('resumo.index', ['mes' => Carbon::parse($data['reference_month'])->format('Y-m')])
            ->with('status', 'Dados mensais salvos.');
    }

    public function edit(MonthlyRevenue $faturamento): View
    {
        $company = $this->company();
        abort_unless($faturamento->company_id === $company->id, 404);

        return view('monthly-revenues.form', [
            'company' => $company,
            'revenue' => $faturamento,
            'referenceMonth' => $faturamento->reference_month->copy()->startOfMonth(),
        ]);
    }

    public function update(Request $request, MonthlyRevenue $faturamento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($faturamento->company_id === $company->id, 404);

        $data = $this->normalizeRevenueData($this->validated($request));
        $data['reference_month'] = Carbon::createFromFormat('Y-m-d', $data['reference_month'] . '-01')->startOfMonth()->toDateString();
        $data['average_ticket'] = $this->averageTicket($data);

        $faturamento->update($data);

        return redirect()->route('resumo.index', ['mes' => Carbon::parse($data['reference_month'])->format('Y-m')])
            ->with('status', 'Dados mensais atualizados.');
    }

    public function destroy(MonthlyRevenue $faturamento): RedirectResponse
    {
        $company = $this->company();
        abort_unless($faturamento->company_id === $company->id, 404);
        $faturamento->delete();

        return redirect()->route('faturamento-mensal.index')->with('status', 'Dados mensais removidos.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'reference_month' => ['required', 'date_format:Y-m'],
            'gross_revenue' => ['required', 'numeric', 'min:0'],
            'revenue_to_receive' => ['nullable', 'numeric', 'min:0'],
            'cost_of_goods_sold' => ['nullable', 'numeric', 'min:0'],
            'cmv_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sales_count' => ['nullable', 'integer', 'min:0'],
            'delivery_sales_count' => ['nullable', 'integer', 'min:0'],
            'delivery_revenue' => ['nullable', 'numeric', 'min:0'],
            'counter_sales_count' => ['nullable', 'integer', 'min:0'],
            'counter_revenue' => ['nullable', 'numeric', 'min:0'],
            'items_per_ticket' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'important_info' => ['nullable', 'string'],
        ]);
    }

    private function normalizeRevenueData(array $data): array
    {
        $data['delivery_sales_count'] = (int) ($data['delivery_sales_count'] ?? 0);
        $data['counter_sales_count'] = (int) ($data['counter_sales_count'] ?? 0);
        $data['delivery_revenue'] = (float) ($data['delivery_revenue'] ?? 0);
        $data['counter_revenue'] = (float) ($data['counter_revenue'] ?? 0);

        $channelSales = $data['delivery_sales_count'] + $data['counter_sales_count'];
        $channelRevenue = $data['delivery_revenue'] + $data['counter_revenue'];

        if ($channelSales > 0) {
            $data['sales_count'] = $channelSales;
        }

        if ($channelRevenue > 0) {
            $data['gross_revenue'] = $channelRevenue;
        }

        return $data;
    }

    private function averageTicket(array $data): float
    {
        $grossRevenue = (float) ($data['gross_revenue'] ?? 0);
        $salesCount = (int) ($data['sales_count'] ?? 0);

        if ($grossRevenue <= 0 || $salesCount <= 0) {
            return 0;
        }

        return round($grossRevenue / $salesCount, 2);
    }

    private function monthFromRequest(Request $request): Carbon
    {
        $month = $request->query('mes');

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
