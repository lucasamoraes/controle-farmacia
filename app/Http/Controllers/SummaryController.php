<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SummaryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $this->company();
        $selectedMonth = $this->selectedMonth($request);
        $previousMonth = $selectedMonth->copy()->subMonth();
        [$monthStart, $monthEnd] = $this->selectedRange($request, $selectedMonth);
        $previousStart = $previousMonth->copy()->startOfMonth();
        $previousEnd = $previousMonth->copy()->endOfMonth();

        $monthlyRevenue = $company->monthlyRevenues()->whereDate('reference_month', $monthStart)->first();
        $previousRevenue = $company->monthlyRevenues()->whereDate('reference_month', $previousStart)->first();

        $allCategoryTotals = $this->categoryTotals($company, $monthStart, $monthEnd);
        $allSupplierTotals = $this->supplierTotals($company, $monthStart, $monthEnd);
        $categoryTotals = $allCategoryTotals;
        $supplierTotals = $allSupplierTotals;
        $monthlyEvolution = $this->monthlyEvolution($company, $selectedMonth);

        $totalExpenses = $allCategoryTotals->sum('total');
        $stockPurchases = $allSupplierTotals->sum('total');
        $grossRevenue = (float) ($monthlyRevenue->gross_revenue ?? 0);
        $previousGrossRevenue = (float) ($previousRevenue->gross_revenue ?? 0);
        $channelSummary = $this->channelSummary($monthlyRevenue);

        return view('summary.index', [
            'company' => $company,
            'selectedMonth' => $selectedMonth,
            'dateStart' => $monthStart,
            'dateEnd' => $monthEnd,
            'previousMonth' => $previousMonth,
            'monthlyRevenue' => $monthlyRevenue,
            'previousRevenue' => $previousRevenue,
            'categoryTotals' => $categoryTotals,
            'supplierTotals' => $supplierTotals,
            'monthlyEvolution' => $monthlyEvolution,
            'totalExpenses' => $totalExpenses,
            'stockPurchases' => $stockPurchases,
            'grossRevenue' => $grossRevenue,
            'previousGrossRevenue' => $previousGrossRevenue,
            'channelSummary' => $channelSummary,
            'expensesVsCurrentRevenue' => $this->ratio($totalExpenses, $grossRevenue),
            'expensesVsPreviousRevenue' => $this->ratio($totalExpenses, $previousGrossRevenue),
            'profitEstimate' => $grossRevenue - $totalExpenses,
        ]);
    }

    private function selectedMonth(Request $request): Carbon
    {
        $month = $request->query('mes');

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function selectedRange(Request $request, Carbon $selectedMonth): array
    {
        $start = $request->query('inicio');
        $end = $request->query('fim');

        if (is_string($start) && is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay()];
        }

        return [$selectedMonth->copy()->startOfMonth(), $selectedMonth->copy()->endOfMonth()];
    }

    private function categoryTotals(Company $company, Carbon $start, Carbon $end, string $search = '')
    {
        return $company->payables()
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->whereBetween('payables.due_date', [$start, $end])
            ->where('payables.status', '!=', 'cancelled')
            ->where('payables.source', '!=', 'credit_card_invoice')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('financial_categories.name', 'like', "%{$search}%")
                        ->orWhere('payables.description', 'like', "%{$search}%");
                });
            })
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->get([
                DB::raw("COALESCE(financial_categories.name, 'Sem categoria') as name"),
                DB::raw('SUM(payables.amount) as total'),
            ])
            ->concat($this->cardItemCategoryTotals($company, $start, $end, $search))
            ->groupBy('name')
            ->map(fn ($rows, $name) => (object) ['name' => $name, 'total' => (float) $rows->sum('total')])
            ->sortByDesc('total')
            ->values();
    }

    private function cardItemCategoryTotals(Company $company, Carbon $start, Carbon $end, string $search = '')
    {
        return DB::table('credit_card_invoice_items')
            ->join('credit_card_invoices', 'credit_card_invoices.id', '=', 'credit_card_invoice_items.credit_card_invoice_id')
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'credit_card_invoice_items.financial_category_id')
            ->where('credit_card_invoices.company_id', $company->id)
            ->whereBetween('credit_card_invoices.due_date', [$start, $end])
            ->where('credit_card_invoices.status', '!=', 'cancelled')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('financial_categories.name', 'like', "%{$search}%")
                        ->orWhere('credit_card_invoice_items.description', 'like', "%{$search}%")
                        ->orWhere('credit_card_invoices.card_name', 'like', "%{$search}%");
                });
            })
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->get([
                DB::raw("COALESCE(financial_categories.name, 'Sem categoria') as name"),
                DB::raw('SUM(credit_card_invoice_items.amount) as total'),
            ]);
    }

    private function supplierTotals(Company $company, Carbon $start, Carbon $end, string $search = '')
    {
        return $company->payables()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'payables.supplier_id')
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->whereBetween('payables.due_date', [$start, $end])
            ->where('payables.status', '!=', 'cancelled')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('suppliers.name', 'like', "%{$search}%")
                        ->orWhere('suppliers.trade_name', 'like', "%{$search}%")
                        ->orWhere('payables.description', 'like', "%{$search}%");
                });
            })
            ->where(function ($query) {
                $query->where('financial_categories.name', 'like', '%mercadoria%')
                    ->orWhere('financial_categories.name', 'like', '%estoque%');
            })
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc(DB::raw('SUM(payables.amount)'))
            ->limit(20)
            ->get([
                DB::raw("COALESCE(suppliers.name, 'Sem fornecedor') as name"),
                DB::raw('SUM(payables.amount) as total'),
            ]);
    }

    private function monthlyEvolution(Company $company, Carbon $selectedMonth): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $selectedMonth->copy()->subMonths($i)->startOfMonth();
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $revenue = $company->monthlyRevenues()->whereDate('reference_month', $start)->first();
            $expenses = (float) $company->payables()
                ->whereBetween('due_date', [$start, $end])
                ->where('status', '!=', 'cancelled')
                ->sum('amount');

            $months[] = [
                'label' => $month->translatedFormat('M/Y'),
                'month' => $month->format('Y-m'),
                'gross_revenue' => (float) ($revenue->gross_revenue ?? 0),
                'expenses' => $expenses,
                'sales_count' => (int) ($revenue->sales_count ?? 0),
                'delivery_sales_count' => (int) ($revenue->delivery_sales_count ?? 0),
                'delivery_revenue' => (float) ($revenue->delivery_revenue ?? 0),
                'counter_sales_count' => (int) ($revenue->counter_sales_count ?? 0),
                'counter_revenue' => (float) ($revenue->counter_revenue ?? 0),
                'cmv_percentage' => (float) ($revenue->cmv_percentage ?? 0),
            ];
        }

        return $months;
    }

    private function ratio(float $value, float $base): ?float
    {
        if ($base <= 0) {
            return null;
        }

        return ($value / $base) * 100;
    }

    private function channelSummary($monthlyRevenue): array
    {
        $deliveryRevenue = (float) ($monthlyRevenue->delivery_revenue ?? 0);
        $counterRevenue = (float) ($monthlyRevenue->counter_revenue ?? 0);
        $deliverySales = (int) ($monthlyRevenue->delivery_sales_count ?? 0);
        $counterSales = (int) ($monthlyRevenue->counter_sales_count ?? 0);
        $totalRevenue = $deliveryRevenue + $counterRevenue;
        $totalSales = $deliverySales + $counterSales;

        return [
            [
                'label' => 'Delivery',
                'sales_count' => $deliverySales,
                'revenue' => $deliveryRevenue,
                'sales_percent' => $this->ratio($deliverySales, $totalSales),
                'revenue_percent' => $this->ratio($deliveryRevenue, $totalRevenue),
                'average_ticket' => $deliverySales > 0 ? $deliveryRevenue / $deliverySales : 0,
            ],
            [
                'label' => 'Balcao',
                'sales_count' => $counterSales,
                'revenue' => $counterRevenue,
                'sales_percent' => $this->ratio($counterSales, $totalSales),
                'revenue_percent' => $this->ratio($counterRevenue, $totalRevenue),
                'average_ticket' => $counterSales > 0 ? $counterRevenue / $counterSales : 0,
            ],
        ];
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
