<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payable;
use Illuminate\Support\Carbon;

class FinancialAlertService
{
    public function dashboardAlerts(Company $company): array
    {
        return array_values(array_filter([
            $this->dueTodayAlert($company),
            $this->expenseRatioAlert($company),
        ]));
    }

    public function currentMonthBoletoAlert(Company $company, Payable $payable): ?array
    {
        if (! $payable->due_date->isSameMonth(Carbon::today())) {
            return null;
        }

        $ratioAlert = $this->expenseRatioAlert($company);

        return [
            'level' => $ratioAlert['level'] ?? 'info',
            'title' => 'Boleto incluido no mes vigente',
            'message' => 'A conta de R$ ' . number_format((float) $payable->amount, 2, ',', '.') . ' foi adicionada ao mes atual. ' . ($ratioAlert['message'] ?? 'Confira o impacto no dashboard.'),
        ];
    }

    private function dueTodayAlert(Company $company): ?array
    {
        $today = Carbon::today();
        $count = $company->payables()
            ->where('status', 'open')
            ->whereDate('due_date', $today)
            ->count();

        if ($count === 0) {
            return null;
        }

        $total = $company->payables()
            ->where('status', 'open')
            ->whereDate('due_date', $today)
            ->sum('amount');

        return [
            'level' => 'warning',
            'title' => 'Boletos vencendo hoje',
            'message' => "{$count} conta(s) em aberto vencem hoje, totalizando R$ " . number_format((float) $total, 2, ',', '.') . '.',
        ];
    }

    private function expenseRatioAlert(Company $company): ?array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $previousStart = $today->copy()->subMonth()->startOfMonth();

        $previousRevenue = (float) ($company->monthlyRevenues()
            ->whereDate('reference_month', $previousStart)
            ->value('gross_revenue') ?? 0);

        if ($previousRevenue <= 0) {
            return null;
        }

        $expenses = (float) $company->payables()
            ->leftJoin('financial_categories', 'financial_categories.id', '=', 'payables.financial_category_id')
            ->whereBetween('payables.due_date', [$monthStart, $monthEnd])
            ->where('payables.status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->where('financial_categories.name', 'like', '%mercadoria%')
                    ->orWhere('financial_categories.name', 'like', '%estoque%');
            })
            ->sum('payables.amount');

        $ratio = ($expenses / $previousRevenue) * 100;
        $threshold = $this->threshold($ratio);

        if ($threshold === null) {
            return null;
        }

        $level = $threshold >= 55 ? 'danger' : 'warning';

        return [
            'level' => $level,
            'title' => $threshold >= 55 ? 'Atenção: despesas acima de 55%' : 'Alerta de despesas',
            'message' => 'As compras de mercadorias do mes atual chegaram a ' . number_format($ratio, 1, ',', '.') . '% do faturamento do mes anterior.',
            'ratio' => $ratio,
            'threshold' => $threshold,
        ];
    }

    private function threshold(float $ratio): ?int
    {
        foreach ([55, 50, 45, 40, 30] as $threshold) {
            if ($ratio >= $threshold) {
                return $threshold;
            }
        }

        return null;
    }
}
