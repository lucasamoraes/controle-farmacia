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
        $dashboardTab = in_array($request->query('aba'), ['financeiro', 'funcionarios', 'vendas'], true)
            ? $request->query('aba')
            : 'financeiro';
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
            ->where('payables.source', '!=', 'credit_card_invoice')
            ->whereNotNull('payables.supplier_id')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc(DB::raw('SUM(payables.amount)'))
            ->limit(5)
            ->get([
                DB::raw('suppliers.name as name'),
                DB::raw('SUM(payables.amount) as total'),
            ]);

        $categoryTotals = $this->categoryTotals($company, $search, $status, $dateStart, $dateEnd)->take(5);

        return view('dashboard.index', [
            'company' => $company,
            'search' => $search,
            'statusFilter' => $status,
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'dashboardTab' => $dashboardTab,
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
            'financeSummary' => $this->financeSummary($company, $dateStart),
            'employeeDashboard' => $this->employeeDashboard($company, $dateStart, $dateEnd),
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
                'delivery_count' => (int) $row->delivery_sales_count,
                'counter_count' => (int) $row->counter_sales_count,
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

    private function categoryTotals(Company $company, string $search, string $status, ?string $dateStart, ?string $dateEnd)
    {
        $payableRows = $this->filteredPayables($company, $search, $status, $dateStart, $dateEnd)
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->where('payables.status', '!=', 'cancelled')
            ->where('payables.source', '!=', 'credit_card_invoice')
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->get([
                DB::raw("COALESCE(financial_categories.name, 'Sem categoria') as name"),
                DB::raw('SUM(payables.amount) as total'),
            ]);

        $cardRows = DB::table('credit_card_invoice_items')
            ->join('credit_card_invoices', 'credit_card_invoices.id', '=', 'credit_card_invoice_items.credit_card_invoice_id')
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'credit_card_invoice_items.financial_category_id')
            ->where('credit_card_invoices.company_id', $company->id)
            ->when($dateStart, fn ($query) => $query->whereDate('credit_card_invoices.due_date', '>=', $dateStart))
            ->when($dateEnd, fn ($query) => $query->whereDate('credit_card_invoices.due_date', '<=', $dateEnd))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('credit_card_invoices.card_name', 'like', "%{$search}%")
                        ->orWhere('credit_card_invoice_items.description', 'like', "%{$search}%")
                        ->orWhere('financial_categories.name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['open', 'paid', 'cancelled', 'overdue'], true), function ($query) use ($status) {
                if ($status === 'overdue') {
                    $query->where('credit_card_invoices.status', 'open')->whereDate('credit_card_invoices.due_date', '<', Carbon::today());
                    return;
                }

                $query->where('credit_card_invoices.status', $status);
            }, fn ($query) => $query->where('credit_card_invoices.status', '!=', 'cancelled'))
            ->groupBy('financial_categories.id', 'financial_categories.name')
            ->get([
                DB::raw("COALESCE(financial_categories.name, 'Sem categoria') as name"),
                DB::raw('SUM(credit_card_invoice_items.amount) as total'),
            ]);

        return $payableRows
            ->concat($cardRows)
            ->groupBy('name')
            ->map(fn ($rows, $name) => (object) ['name' => $name, 'total' => (float) $rows->sum('total')])
            ->sortByDesc('total')
            ->values();
    }

    private function employeeDashboard(Company $company, ?string $dateStart, ?string $dateEnd): array
    {
        $activeEmployees = $company->employees()->where('is_active', true)->get();
        $monthStart = $dateStart ? Carbon::parse($dateStart)->startOfMonth() : now()->startOfMonth();
        $monthEnd = $dateEnd ? Carbon::parse($dateEnd)->endOfMonth() : now()->endOfMonth();
        $baseTotal = (float) $activeEmployees->sum('base_salary');
        $months = collect();

        for ($month = $monthStart->copy(); $month->lte($monthEnd); $month->addMonth()) {
            $months->push($month->copy());
        }

        $monthly = $months->map(fn ($month) => [
            'label' => $month->format('m/Y'),
            'fixed' => $baseTotal,
            'variable' => 0,
            'total' => $baseTotal,
        ]);

        $advanceTotal = (float) DB::table('employee_advances')
            ->join('employees', 'employees.id', '=', 'employee_advances.employee_id')
            ->where('employees.company_id', $company->id)
            ->whereBetween('employee_advances.deduct_month', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('employee_advances.amount');
        $movementTypes = DB::table('employee_payroll_items')
            ->join('employees', 'employees.id', '=', 'employee_payroll_items.employee_id')
            ->leftJoin('employee_movement_types', function ($join) use ($company) {
                $join->on('employee_movement_types.code', '=', 'employee_payroll_items.event_type')
                    ->where('employee_movement_types.company_id', '=', $company->id);
            })
            ->where('employees.company_id', $company->id)
            ->whereBetween('employee_payroll_items.reference_month', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('employee_payroll_items.event_type', 'employee_movement_types.name', 'employee_movement_types.kind')
            ->orderByDesc(DB::raw('SUM(employee_payroll_items.earning + employee_payroll_items.deduction)'))
            ->get([
                DB::raw("COALESCE(employee_movement_types.name, employee_payroll_items.event_type, 'Movimento') as label"),
                DB::raw("COALESCE(employee_movement_types.kind, CASE WHEN SUM(employee_payroll_items.deduction) > SUM(employee_payroll_items.earning) THEN 'debit' ELSE 'credit' END) as kind"),
                DB::raw('SUM(employee_payroll_items.earning) as earnings'),
                DB::raw('SUM(employee_payroll_items.deduction) as deductions'),
                DB::raw('SUM(employee_payroll_items.earning + employee_payroll_items.deduction) as total'),
            ])
            ->map(fn ($row) => [
                'label' => $row->label,
                'kind' => $row->kind,
                'earnings' => (float) $row->earnings,
                'deductions' => (float) $row->deductions,
                'total' => (float) $row->total,
            ])
            ->all();

        return [
            'activeCount' => $activeEmployees->count(),
            'inactiveCount' => $company->employees()->where('is_active', false)->count(),
            'fixedTotal' => $baseTotal,
            'variableTotal' => $advanceTotal,
            'openTotal' => max(0, $baseTotal - $advanceTotal),
            'paidTotal' => 0,
            'monthly' => $monthly->all(),
            'movementTypes' => $movementTypes,
            'topEmployees' => $activeEmployees
                ->map(fn ($employee) => ['name' => $employee->name, 'total' => (float) $employee->base_salary])
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function financeSummary(Company $company, ?string $dateStart): array
    {
        $month = $dateStart
            ? Carbon::parse($dateStart)->startOfMonth()
            : now()->startOfMonth();
        $previousMonth = $month->copy()->subMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $revenue = $company->monthlyRevenues()->whereDate('reference_month', $monthStart)->first();
        $previousRevenue = $company->monthlyRevenues()->whereDate('reference_month', $previousMonth->copy()->startOfMonth())->first();
        $expenses = (float) $company->payables()
            ->whereBetween('due_date', [$monthStart, $monthEnd])
            ->where('status', '!=', 'cancelled')
            ->sum('amount');
        $stockPurchases = (float) $company->payables()
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->whereBetween('payables.due_date', [$monthStart, $monthEnd])
            ->where('payables.status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->where('financial_categories.name', 'like', '%mercadoria%')
                    ->orWhere('financial_categories.name', 'like', '%estoque%');
            })
            ->sum('payables.amount');
        $grossRevenue = (float) ($revenue->gross_revenue ?? 0);
        $previousGrossRevenue = (float) ($previousRevenue->gross_revenue ?? 0);

        return [
            'monthLabel' => $month->format('m/Y'),
            'grossRevenue' => $grossRevenue,
            'expenses' => $expenses,
            'stockPurchases' => $stockPurchases,
            'profitEstimate' => $grossRevenue - $expenses,
            'expensesVsRevenue' => $grossRevenue > 0 ? ($expenses / $grossRevenue) * 100 : null,
            'expensesVsPreviousRevenue' => $previousGrossRevenue > 0 ? ($expenses / $previousGrossRevenue) * 100 : null,
            'salesCount' => (int) ($revenue->sales_count ?? 0),
            'averageTicket' => (float) ($revenue->average_ticket ?? 0),
            'cmvPercentage' => (float) ($revenue->cmv_percentage ?? 0),
        ];
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
