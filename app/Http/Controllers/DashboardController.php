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
        [$dateStart, $dateEnd] = $this->dateRange($request, $company);

        if (($dateStart || $dateEnd) && ($request->query('periodo') === null || $period === 'custom')) {
            $period = 'custom';
        }
        $today = $this->operationalDate($company);

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
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->where('payables.status', '!=', 'cancelled')
            ->where('payables.source', '!=', 'credit_card_invoice')
            ->whereNotNull('payables.supplier_id')
            ->where(function ($query) {
                $query->where('financial_categories.name', 'like', '%mercadoria%')
                    ->orWhere('financial_categories.name', 'like', '%estoque%');
            })
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
            'revenueProjection' => $this->revenueProjection($company),
            'salesPeriodComparisonChart' => $this->salesPeriodComparisonChart($company),
            'weekdayAverageChart' => $this->weekdayAverageChart($company, $dateStart, $dateEnd),
            'channelRevenueChart' => $this->channelRevenueChart($company),
            'monthlyExpenseChart' => $this->monthlyExpenseChart($company),
            'financeSummary' => $this->financeSummary($company, $dateStart),
            'employeeDashboard' => $this->employeeDashboard($company, $dateStart, $dateEnd, $request->integer('funcionario') ?: null),
            'financialAlerts' => $alerts->dashboardAlerts($company),
            'suppliersCount' => $company->suppliers()->count(),
        ]);
    }

    private function dateRange(Request $request, Company $company): array
    {
        $period = $request->query('periodo');
        $today = $this->operationalDate($company);
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
            ->when(in_array($status, ['open', 'paid', 'cancelled', 'overdue'], true), function ($query) use ($status, $company) {
                if ($status === 'overdue') {
                    $query->where('status', 'open')->whereDate('due_date', '<', $this->operationalDate($company));
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
        $operationalDate = $this->operationalDate($company);
        $operationalMonth = $operationalDate->copy()->startOfMonth();
        $yearStart = $operationalMonth->copy()->startOfYear();
        $rows = $company->monthlyRevenues()
            ->whereBetween('reference_month', [$yearStart->toDateString(), $operationalMonth->toDateString()])
            ->where('gross_revenue', '>', 0)
            ->orderBy('reference_month')
            ->get()
            ->keyBy(fn ($row) => $row->reference_month->format('Y-m'));
        $previous = null;
        $months = collect();

        foreach ($rows as $key => $row) {
            $month = $row->reference_month->copy()->startOfMonth();
            $row = $rows->get($month->format('Y-m'));
            $value = (float) ($row->gross_revenue ?? 0);
            $currentProjection = $month->equalTo($operationalMonth) ? $this->currentMonthRevenueProjection($company, $value) : null;
            $actual = $currentProjection['actual'] ?? $value;
            $projectedRemaining = $currentProjection['remaining'] ?? 0;
            $projectedTotal = $actual + $projectedRemaining;
            $growthBase = $month->equalTo($operationalMonth) ? $actual : $value;
            $growth = $previous && $previous > 0 ? (($growthBase - $previous) / $previous) * 100 : null;
            if ($value > 0 && $month->lt($operationalMonth)) {
                $previous = $value;
            }

            $months->push([
                'label' => $month->format('m/Y'),
                'sort' => $month->format('Y-m'),
                'value' => $projectedTotal,
                'actual' => $actual,
                'projected_remaining' => $projectedRemaining,
                'projected_total' => $projectedTotal,
                'is_current' => $month->equalTo($operationalMonth),
                'days_recorded' => $currentProjection['days_recorded'] ?? null,
                'days_remaining' => $currentProjection['days_remaining'] ?? null,
                'daily_average' => $currentProjection['daily_average'] ?? null,
                'growth' => $growth,
            ]);
        }

        if (! $rows->has($operationalMonth->format('Y-m'))) {
            $currentProjection = $this->currentMonthRevenueProjection($company, 0);
            if ($currentProjection['actual'] > 0) {
                $months->push([
                    'label' => $operationalMonth->format('m/Y'),
                    'sort' => $operationalMonth->format('Y-m'),
                    'value' => $currentProjection['total'],
                    'actual' => $currentProjection['actual'],
                    'projected_remaining' => $currentProjection['remaining'],
                    'projected_total' => $currentProjection['total'],
                    'is_current' => true,
                    'days_recorded' => $currentProjection['days_recorded'],
                    'days_remaining' => $currentProjection['days_remaining'],
                    'daily_average' => $currentProjection['daily_average'],
                    'growth' => $previous && $previous > 0 ? (($currentProjection['actual'] - $previous) / $previous) * 100 : null,
                ]);
            }
        }

        return $months->reverse()->values()->all();
    }

    private function revenueProjection(Company $company): array
    {
        $operationalMonth = $this->operationalDate($company)->startOfMonth();
        $rows = $company->monthlyRevenues()
            ->where('gross_revenue', '>', 0)
            ->orderBy('reference_month')
            ->get();
        $closedRows = $rows
            ->filter(fn ($row) => $row->reference_month->copy()->startOfMonth()->lt($operationalMonth))
            ->values();
        $growths = [];
        $previous = null;

        foreach ($closedRows as $row) {
            $value = (float) $row->gross_revenue;
            if ($previous && $previous > 0) {
                $growths[] = ($value - $previous) / $previous;
            }
            $previous = $value;
        }

        $averageGrowth = count($growths) > 0 ? array_sum($growths) / count($growths) : 0;
        $currentProjection = $this->currentMonthRevenueProjection($company);
        $nextMonth = $operationalMonth->copy()->addMonth();
        $projectedNext = $currentProjection['total'] > 0 ? $currentProjection['total'] * (1 + $averageGrowth) : 0;
        $maxClosedRevenue = (float) $closedRows->max('gross_revenue');

        return [
            'averageGrowth' => $averageGrowth * 100,
            'nextMonthLabel' => $nextMonth->format('m/Y'),
            'projectedNextRevenue' => max(0, $projectedNext),
            'maxClosedRevenue' => $maxClosedRevenue,
            'closedMonthsCount' => $closedRows->count(),
            'currentMonthActual' => $currentProjection['actual'],
            'currentMonthRemainingProjection' => $currentProjection['remaining'],
            'currentMonthProjection' => $currentProjection['total'],
            'currentMonthDaysRecorded' => $currentProjection['days_recorded'],
            'currentMonthDaysRemaining' => $currentProjection['days_remaining'],
        ];
    }

    private function currentMonthRevenueProjection(Company $company, ?float $fallbackActual = null): array
    {
        $anchorDate = $this->operationalDate($company);
        $currentMonth = $anchorDate->copy()->startOfMonth();
        $currentSales = $company->dailySales()
            ->whereBetween('sale_date', [$currentMonth->toDateString(), $anchorDate->toDateString()])
            ->get();
        $actual = $currentSales->count() > 0 ? (float) $currentSales->sum('amount') : (float) ($fallbackActual ?? 0);
        $daysRecorded = $currentSales->count();
        $daysRemaining = max(0, $currentMonth->daysInMonth - (int) $anchorDate->format('d'));
        $dailyAverage = $daysRecorded > 0 ? $actual / $daysRecorded : 0;
        $remaining = $daysRecorded > 0 ? $dailyAverage * $daysRemaining : 0;

        return [
            'actual' => max(0, $actual),
            'remaining' => max(0, $remaining),
            'total' => max(0, $actual + $remaining),
            'days_recorded' => $daysRecorded,
            'days_remaining' => $daysRemaining,
            'daily_average' => $dailyAverage,
            'anchor_date' => $anchorDate->toDateString(),
        ];
    }

    private function operationalDate(Company $company): Carbon
    {
        $lastSaleDate = $company->dailySales()->max('sale_date');

        return $lastSaleDate ? Carbon::parse($lastSaleDate)->startOfDay() : Carbon::today();
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

    private function salesPeriodComparisonChart(Company $company): array
    {
        $operationalDate = $this->operationalDate($company);
        $operationalMonth = $operationalDate->copy()->startOfMonth();
        $yearStart = $operationalMonth->copy()->startOfYear();
        $sales = $company->dailySales()
            ->whereBetween('sale_date', [$yearStart->toDateString(), $operationalDate->toDateString()])
            ->orderBy('sale_date')
            ->get()
            ->groupBy(fn ($sale) => $sale->sale_date->format('Y-m'));

        $months = collect();

        foreach ($sales as $monthKey => $rows) {
            $month = Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->startOfMonth();
            $periods = [
                'first' => (float) $rows->filter(fn ($sale) => (int) $sale->sale_date->format('d') <= 10)->sum('amount'),
                'second' => (float) $rows->filter(fn ($sale) => (int) $sale->sale_date->format('d') >= 11 && (int) $sale->sale_date->format('d') <= 20)->sum('amount'),
                'third' => (float) $rows->filter(fn ($sale) => (int) $sale->sale_date->format('d') >= 21)->sum('amount'),
            ];

            $months->push([
                'label' => $month->format('m/Y'),
                'sort' => $month->format('Y-m'),
                'is_current' => $month->equalTo($operationalMonth),
                'last_day_recorded' => $month->equalTo($operationalMonth) ? (int) $operationalDate->format('d') : $month->daysInMonth,
                'first' => $periods['first'],
                'second' => $periods['second'],
                'third' => $periods['third'],
                'total' => array_sum($periods),
            ]);
        }

        return $months
            ->filter(fn ($row) => $row['total'] > 0)
            ->sortByDesc('sort')
            ->values()
            ->all();
    }

    private function channelRevenueChart(Company $company): array
    {
        $today = $this->operationalDate($company)->startOfMonth();
        $yearStart = $today->copy()->startOfYear();

        return $company->monthlyRevenues()
            ->whereBetween('reference_month', [$yearStart->toDateString(), $today->toDateString()])
            ->where('gross_revenue', '>', 0)
            ->orderByDesc('reference_month')
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
        $operationalMonth = $this->operationalDate($company)->startOfMonth();
        $yearStart = $operationalMonth->copy()->startOfYear();
        $months = $company->payables()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$yearStart->toDateString(), $operationalMonth->copy()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy(fn ($payable) => $payable->due_date->format('Y-m'));
        $revenues = $company->monthlyRevenues()
            ->whereBetween('reference_month', [$yearStart->toDateString(), $operationalMonth->toDateString()])
            ->get()
            ->keyBy(fn ($row) => $row->reference_month->format('Y-m'));
        $chart = collect();

        for ($month = $yearStart->copy(); $month->lte($operationalMonth); $month->addMonth()) {
            $key = $month->format('Y-m');
            $expense = (float) ($months->get($key, collect())->sum('amount'));
            $revenue = (float) ($revenues->get($key)->gross_revenue ?? 0);
            if ($expense <= 0 && $revenue <= 0) {
                continue;
            }
            $chart->push([
                'label' => $month->format('m/Y'),
                'sort' => $key,
                'value' => $expense,
                'revenue' => $revenue,
                'percent' => $revenue > 0 ? ($expense / $revenue) * 100 : null,
            ]);
        }

        return $chart->reverse()->values()->all();
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
            ->when(in_array($status, ['open', 'paid', 'cancelled', 'overdue'], true), function ($query) use ($status, $company) {
                if ($status === 'overdue') {
                    $query->where('credit_card_invoices.status', 'open')->whereDate('credit_card_invoices.due_date', '<', $this->operationalDate($company));
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

    private function employeeDashboard(Company $company, ?string $dateStart, ?string $dateEnd, ?int $selectedEmployeeId = null): array
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

        $selectedEmployee = $selectedEmployeeId
            ? $activeEmployees->firstWhere('id', $selectedEmployeeId)
            : $activeEmployees->first();
        $employeeMovementDetails = $selectedEmployee
            ? $this->employeeMovementDetails($company, (int) $selectedEmployee->id, $monthStart, $monthEnd)
            : [];

        return [
            'activeCount' => $activeEmployees->count(),
            'inactiveCount' => $company->employees()->where('is_active', false)->count(),
            'fixedTotal' => $baseTotal,
            'variableTotal' => $advanceTotal,
            'openTotal' => max(0, $baseTotal - $advanceTotal),
            'paidTotal' => 0,
            'monthly' => $monthly->all(),
            'movementTypes' => $movementTypes,
            'selectedEmployeeId' => $selectedEmployee?->id,
            'selectedEmployeeName' => $selectedEmployee?->name,
            'employeeMovementDetails' => $employeeMovementDetails,
            'topEmployees' => $activeEmployees
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'total' => (float) $employee->base_salary,
                ])
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function employeeMovementDetails(Company $company, int $employeeId, Carbon $monthStart, Carbon $monthEnd): array
    {
        return DB::table('employee_payroll_items')
            ->join('employees', 'employees.id', '=', 'employee_payroll_items.employee_id')
            ->leftJoin('employee_movement_types', function ($join) use ($company) {
                $join->on('employee_movement_types.code', '=', 'employee_payroll_items.event_type')
                    ->where('employee_movement_types.company_id', '=', $company->id);
            })
            ->where('employees.company_id', $company->id)
            ->where('employees.id', $employeeId)
            ->whereBetween('employee_payroll_items.reference_month', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('employee_payroll_items.event_type', 'employee_movement_types.name', 'employee_movement_types.kind')
            ->orderByDesc(DB::raw('SUM(employee_payroll_items.earning + employee_payroll_items.deduction)'))
            ->get([
                DB::raw("COALESCE(employee_movement_types.name, employee_payroll_items.event_type, 'Movimento') as label"),
                DB::raw("COALESCE(employee_movement_types.kind, CASE WHEN SUM(employee_payroll_items.deduction) > SUM(employee_payroll_items.earning) THEN 'debit' ELSE 'credit' END) as kind"),
                DB::raw('SUM(employee_payroll_items.earning) as earnings'),
                DB::raw('SUM(employee_payroll_items.deduction) as deductions'),
            ])
            ->map(fn ($row) => [
                'label' => $row->label,
                'kind' => $row->kind,
                'earnings' => (float) $row->earnings,
                'deductions' => (float) $row->deductions,
                'total' => (float) $row->earnings + (float) $row->deductions,
            ])
            ->all();
    }

    private function financeSummary(Company $company, ?string $dateStart): array
    {
        $month = $dateStart
            ? Carbon::parse($dateStart)->startOfMonth()
            : $this->operationalDate($company)->startOfMonth();
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
        $projection = $month->equalTo($this->operationalDate($company)->startOfMonth())
            ? $this->currentMonthRevenueProjection($company, $grossRevenue)
            : ['total' => $grossRevenue, 'actual' => $grossRevenue, 'remaining' => 0, 'days_recorded' => null, 'days_remaining' => null];
        $projectedRevenue = (float) $projection['total'];

        return [
            'monthLabel' => $month->format('m/Y'),
            'grossRevenue' => $grossRevenue,
            'projectedRevenue' => $projectedRevenue,
            'projectedRemainingRevenue' => (float) $projection['remaining'],
            'projectionDaysRecorded' => $projection['days_recorded'],
            'projectionDaysRemaining' => $projection['days_remaining'],
            'expenses' => $expenses,
            'stockPurchases' => $stockPurchases,
            'profitEstimate' => $projectedRevenue - $expenses,
            'expensesVsRevenue' => $projectedRevenue > 0 ? ($expenses / $projectedRevenue) * 100 : null,
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
