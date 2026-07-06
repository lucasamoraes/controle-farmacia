<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $company = $this->company();
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $payables = $company->payables();

        return view('dashboard.index', [
            'company' => $company,
            'openTotal' => (clone $payables)->where('status', 'open')->sum('amount'),
            'overdueTotal' => (clone $payables)->where('status', 'open')->whereDate('due_date', '<', $today)->sum('amount'),
            'monthTotal' => (clone $payables)->whereBetween('due_date', [$monthStart, $monthEnd])->sum('amount'),
            'paidMonthTotal' => (clone $payables)->where('status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount'),
            'upcomingPayables' => $company->payables()->with('supplier')->where('status', 'open')->orderBy('due_date')->limit(8)->get(),
            'suppliersCount' => $company->suppliers()->count(),
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
