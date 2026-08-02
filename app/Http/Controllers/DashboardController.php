<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\FinancialAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialAlertService $alerts): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));
        $status = $request->query('status', '');
        $period = $request->query('periodo', 'month');
        [$dateStart, $dateEnd] = $this->dateRange($request);

        if (($dateStart || $dateEnd) && ($request->query('periodo') === null || $period === 'custom')) {
            $period = 'custom';
        }
        $today = Carbon::today();

        $filtered = $this->filteredPayables($company, $search, $status, $dateStart, $dateEnd);
        $statusBase = $this->filteredPayables($company, $search, '', $dateStart, $dateEnd);

        $totalFiltered = (clone $filtered)->sum('amount');
        $openTotal = (clone $statusBase)->where('status', 'open')->sum('amount');
        $paidTotal = (clone $statusBase)->where('status', 'paid')->sum('amount');
        $cancelledTotal = (clone $statusBase)->where('status', 'cancelled')->sum('amount');
        $overdueTotal = (clone $statusBase)->where('status', 'open')->whereDate('due_date', '<', $today)->sum('amount');
        $monthTotal = (clone $statusBase)->where('status', '!=', 'cancelled')->sum('amount');

        $statusTotals = [
            ['label' => 'Aberto', 'value' => (float) $openTotal, 'color' => 'var(--brand)'],
            ['label' => 'Pago', 'value' => (float) $paidTotal, 'color' => '#2563eb'],
            ['label' => 'Vencido', 'value' => (float) $overdueTotal, 'color' => 'var(--danger)'],
            ['label' => 'Cancelado', 'value' => (float) $cancelledTotal, 'color' => '#64748b'],
        ];

        $topSuppliers = (clone $filtered)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'payables.supplier_id')
            ->where('payables.status', '!=', 'cancelled')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc(DB::raw('SUM(payables.amount)'))
            ->limit(5)
            ->get([
                DB::raw("COALESCE(suppliers.name, 'Sem fornecedor') as name"),
                DB::raw('SUM(payables.amount) as total'),
            ]);

        $categoryTotals = (clone $filtered)
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->where('payables.status', '!=', 'cancelled')
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->orderByDesc(DB::raw('SUM(payables.amount)'))
            ->limit(5)
            ->get([
                DB::raw("COALESCE(financial_categories.name, 'Sem categoria') as name"),
                DB::raw('SUM(payables.amount) as total'),
            ]);

        return view('dashboard.index', [
            'company' => $company,
            'search' => $search,
            'statusFilter' => $status,
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'totalFiltered' => $totalFiltered,
            'openTotal' => $openTotal,
            'overdueTotal' => $overdueTotal,
            'monthTotal' => $monthTotal,
            'paidMonthTotal' => $paidTotal,
            'statusTotals' => $statusTotals,
            'topSuppliers' => $topSuppliers,
            'categoryTotals' => $categoryTotals,
            'monthlyRevenueChart' => $this->monthlyRevenueChart($company),
            'weekdayAverageChart' => $this->weekdayAverageChart($company, $dateStart, $dateEnd),
            'channelRevenueChart' => $this->channelRevenueChart($company),
            'monthlyExpenseChart' => $this->monthlyExpenseChart($company),
            'financialAlerts' => $alerts->dashboardAlerts($company),
            'suppliersCount' => $company->suppliers()->count(),
        ]);
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
            'all' => [null, null],
            'next7' => [$today->toDateString(), $today->copy()->addDays(7)->toDateString()],
            '7' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            '30' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
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

    private function filteredPayables(Company $company, string $search, string $status, ?string $dateStart, ?string $dateEnd)
    {
        return $company->payables()
            ->when($dateStart, fn ($query) => $query->whereDate('due_date', '>=', $dateStart))
            ->when($dateEnd, fn ($query) => $query->whereDate('due_date', '<=', $dateEnd))
            ->when($search !== '', fn ($query) => $this->applyPayableSearch($query, $search))
            ->when(in_array($status, ['open', 'paid', 'cancelled', 'overdue'], true), function ($query) use ($status) {
                if ($status === 'overdue') {
                    $query->where('status', 'open')->whereDate('due_date', '<', Carbon::today());
                    return;
                }

                $query->where('status', $status);
            });
    }

    private function applyPayableSearch($query, string $search): void
    {
        $query->where(function ($inner) use ($search) {
            $inner->where('description', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($supplier) use ($search) {
                    $supplier->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
        });
    }

    private function monthlyRevenueChart(Company $company): array
    {
        $rows = $company->monthlyRevenues()->orderBy('reference_month')->get();
        $previous = null;

        return $rows->map(function ($row) use (&$previous) {
            $value = (float) $row->gross_revenue;
            $growth = $previous && $previous > 0 ? (($value - $previous) / $previous) * 100 : null;
            $previous = $value;

            return [
                'label' => $row->reference_month->format('m/Y'),
                'value' => $value,
                'growth' => $growth,
            ];
        })->all();
    }

    private function weekdayAverageChart(Company $company, ?string $dateStart, ?string $dateEnd): array
    {
        $labels = ['domingo', 'segunda-feira', 'terca-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sabado'];
        $query = $company->dailySales()
            ->when($dateStart, fn ($q) => $q->whereDate('sale_date', '>=', $dateStart))
            ->when($dateEnd, fn ($q) => $q->whereDate('sale_date', '<=', $dateEnd));

        $sales = $query->get()->groupBy(fn ($sale) => $this->weekdayKey($sale->weekday ?: $sale->sale_date->locale('pt_BR')->translatedFormat('l')));

        return collect($labels)->map(function ($label) use ($sales) {
            $rows = $sales->get($label, collect());

            return [
                'label' => ucfirst(str_replace('-feira', '', $label)),
                'value' => $rows->avg('amount') ?: 0,
                'count' => $rows->count(),
            ];
        })->all();
    }

    private function channelRevenueChart(Company $company): array
    {
        return $company->monthlyRevenues()
            ->orderBy('reference_month')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->reference_month->format('m/Y'),
                'delivery' => (float) $row->delivery_revenue,
                'counter' => (float) $row->counter_revenue,
            ])
            ->all();
    }

    private function monthlyExpenseChart(Company $company): array
    {
        $months = $company->payables()
            ->where('status', '!=', 'cancelled')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn ($payable) => $payable->due_date->format('Y-m'));

        return $months->map(fn ($rows, $month) => [
            'label' => Carbon::createFromFormat('Y-m-d', $month . '-01')->format('m/Y'),
            'value' => (float) $rows->sum('amount'),
        ])->values()->all();
    }

    private function weekdayKey(string $weekday): string
    {
        $text = mb_strtolower(trim($weekday));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $converted !== false ? $converted : $text;
        $text = str_replace(' ', '-', $text);

        return match ($text) {
            'domingo' => 'domingo',
            'segunda', 'segunda-feira' => 'segunda-feira',
            'terca', 'terca-feira' => 'terca-feira',
            'quarta', 'quarta-feira' => 'quarta-feira',
            'quinta', 'quinta-feira' => 'quinta-feira',
            'sexta', 'sexta-feira' => 'sexta-feira',
            'sabado' => 'sabado',
            default => $text,
        };
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
