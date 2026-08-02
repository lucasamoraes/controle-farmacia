<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\DailySalesSpreadsheetImporter;
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

        return redirect()->route('imports.vendas-diarias.create')->with('import_result', $stats);
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
}
