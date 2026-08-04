<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\DailySalesSpreadsheetImporter;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailySalesImportController extends Controller
{
    public function create(): View
    {
        return view('imports.daily-sales', [
            'company' => $this->company(),
            'recentSales' => $this->company()->dailySales()->orderByDesc('sale_date')->limit(12)->get(),
        ]);
    }

    public function store(Request $request, DailySalesSpreadsheetImporter $importer): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $path = $data['spreadsheet']->store('imports');
        $stats = $importer->import($company, storage_path('app/private/' . $path));
        foreach ($stats['months'] ?? [] as $month) {
            $this->syncMonthlyRevenue($company, $month);
        }

        return redirect()->route('imports.vendas-diarias.create')->with('import_result', $stats);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'sale_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'weekday' => ['nullable', 'string', 'max:255'],
        ]);

        $saleDate = Carbon::parse($data['sale_date'])->toDateString();
        $weekday = trim((string) ($data['weekday'] ?? ''));
        $weekday = $weekday !== ''
            ? $weekday
            : Carbon::parse($saleDate)->locale('pt_BR')->translatedFormat('l');

        $sale = $company->dailySales()->whereDate('sale_date', $saleDate)->first();

        if ($sale) {
            $sale->update([
                'amount' => round((float) $data['amount'], 2),
                'weekday' => mb_substr($weekday, 0, 255),
            ]);
        } else {
            $sale = $company->dailySales()->create([
                'sale_date' => $saleDate,
                'amount' => round((float) $data['amount'], 2),
                'weekday' => mb_substr($weekday, 0, 255),
            ]);
        }

        $this->syncMonthlyRevenue($company, Carbon::parse($saleDate)->format('Y-m'));

        $message = $sale->wasRecentlyCreated
            ? 'Venda diaria cadastrada e faturamento mensal atualizado.'
            : 'Essa data ja existia. Valor atualizado e faturamento mensal recalculado.';

        return redirect()->route('imports.vendas-diarias.create')->with('status', $message);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('VENDAS');
        $sheet->fromArray(['DATA', 'VALOR', 'DIA DA SEMANA'], null, 'A1');
        $sheet->fromArray(['01/02/2026', 1500.75, 'domingo'], null, 'A2');

        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'modelo-vendas-diarias.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }

    private function syncMonthlyRevenue(Company $company, string $month): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return;
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $grossRevenue = (float) $company->dailySales()
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $revenue = $company->monthlyRevenues()
            ->whereDate('reference_month', $monthStart->toDateString())
            ->first();

        if (! $revenue) {
            $revenue = $company->monthlyRevenues()->make([
                'reference_month' => $monthStart->toDateString(),
            ]);
        }

        $revenue->gross_revenue = $grossRevenue;
        $salesCount = (int) ($revenue->sales_count ?? 0);
        $revenue->average_ticket = $salesCount > 0 ? round($grossRevenue / $salesCount, 2) : 0;
        $revenue->save();
    }
}
