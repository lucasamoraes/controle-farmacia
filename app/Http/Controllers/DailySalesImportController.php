<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DailySale;
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
        [$saleDate, $payload] = $this->validatedSaleData($request);

        $sale = $company->dailySales()->whereDate('sale_date', $saleDate)->first();

        if ($sale) {
            $sale->update($payload);
        } else {
            $sale = $company->dailySales()->create($payload);
        }

        $this->syncMonthlyRevenue($company, Carbon::parse($saleDate)->format('Y-m'));

        $message = $sale->wasRecentlyCreated
            ? 'Venda diaria cadastrada e faturamento mensal atualizado.'
            : 'Essa data ja existia. Valor atualizado e faturamento mensal recalculado.';

        return redirect()->route('imports.vendas-diarias.create')->with('status', $message);
    }

    public function update(Request $request, DailySale $venda): RedirectResponse
    {
        $company = $this->company();
        abort_unless($venda->company_id === $company->id, 404);

        $oldMonth = $venda->sale_date->format('Y-m');
        [$saleDate, $payload] = $this->validatedSaleData($request);
        $existingSale = $company->dailySales()
            ->whereDate('sale_date', $saleDate)
            ->whereKeyNot($venda->id)
            ->first();

        if ($existingSale) {
            return back()->withErrors(['sale_date' => 'Ja existe outro lancamento para essa data. Edite o registro dessa data ou escolha outra.'])->withInput();
        }

        $venda->update($payload);
        $this->syncMonthlyRevenue($company, $oldMonth);
        $this->syncMonthlyRevenue($company, Carbon::parse($saleDate)->format('Y-m'));

        return redirect()->route('imports.vendas-diarias.create')->with('status', 'Venda diaria atualizada e faturamento mensal recalculado.');
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

    private function validatedSaleData(Request $request): array
    {
        $data = $request->validate([
            'sale_date' => ['required', 'date'],
            'delivery_sales_count' => ['nullable', 'integer', 'min:0'],
            'delivery_revenue' => ['nullable', 'numeric', 'min:0'],
            'counter_sales_count' => ['nullable', 'integer', 'min:0'],
            'counter_revenue' => ['nullable', 'numeric', 'min:0'],
            'weekday' => ['nullable', 'string', 'max:255'],
        ]);

        $saleDate = Carbon::parse($data['sale_date'])->toDateString();
        $weekday = trim((string) ($data['weekday'] ?? ''));
        $weekday = $weekday !== ''
            ? $weekday
            : Carbon::parse($saleDate)->locale('pt_BR')->translatedFormat('l');
        $deliveryRevenue = round((float) ($data['delivery_revenue'] ?? 0), 2);
        $counterRevenue = round((float) ($data['counter_revenue'] ?? 0), 2);
        $amount = $deliveryRevenue + $counterRevenue;

        if ($amount <= 0) {
            back()->withErrors(['amount' => 'Informe pelo menos um valor de venda.'])->withInput()->throwResponse();
        }

        return [$saleDate, [
            'sale_date' => $saleDate,
            'amount' => $amount,
            'delivery_sales_count' => (int) ($data['delivery_sales_count'] ?? 0),
            'delivery_revenue' => $deliveryRevenue,
            'counter_sales_count' => (int) ($data['counter_sales_count'] ?? 0),
            'counter_revenue' => $counterRevenue,
            'weekday' => mb_substr($weekday, 0, 255),
        ]];
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
        $deliveryRevenue = (float) $company->dailySales()
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('delivery_revenue');
        $counterRevenue = (float) $company->dailySales()
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('counter_revenue');
        $deliverySalesCount = (int) $company->dailySales()
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('delivery_sales_count');
        $counterSalesCount = (int) $company->dailySales()
            ->whereBetween('sale_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('counter_sales_count');

        $revenue = $company->monthlyRevenues()
            ->whereDate('reference_month', $monthStart->toDateString())
            ->first();

        if (! $revenue) {
            $revenue = $company->monthlyRevenues()->make([
                'reference_month' => $monthStart->toDateString(),
            ]);
        }

        $revenue->gross_revenue = $grossRevenue;
        $revenue->delivery_revenue = $deliveryRevenue;
        $revenue->counter_revenue = $counterRevenue;
        $revenue->delivery_sales_count = $deliverySalesCount;
        $revenue->counter_sales_count = $counterSalesCount;
        $revenue->sales_count = $deliverySalesCount + $counterSalesCount;
        $salesCount = (int) $revenue->sales_count;
        $revenue->average_ticket = $salesCount > 0 ? round($grossRevenue / $salesCount, 2) : 0;
        $revenue->save();
    }
}
