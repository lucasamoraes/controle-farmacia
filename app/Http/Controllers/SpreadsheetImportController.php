<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\BoletoSpreadsheetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetImportController extends Controller
{
    public function create(): View
    {
        return view('imports.boletos', [
            'company' => $this->company(),
        ]);
    }

    public function store(Request $request, BoletoSpreadsheetImporter $importer): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $path = $data['spreadsheet']->store('imports');
        $stats = $importer->import($company, storage_path('app/private/' . $path));

        return redirect()->route('imports.boletos.create')->with('import_result', $stats);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BOLETOS');
        $headers = ['DATA DE VENCIMENTO', 'FORNECEDOR', 'CNPJ', 'CONTA DE PAGAMENTO', 'CATEGORIA', 'VALOR', 'NOTA FISCAL'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([now()->addDays(7)->format('d/m/Y'), 'FORNECEDOR EXEMPLO LTDA', '00123456000199', 'Banco do Brasil', 'Compra de mercadoria', 123.45, '123456'], null, 'A2');

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'modelo-importacao-boletos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
