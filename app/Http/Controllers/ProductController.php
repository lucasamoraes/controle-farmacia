<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company();
        $search = trim((string) $request->query('busca', ''));
        $selectedClasses = array_values(array_filter((array) $request->query('classes', [])));

        return view('products.index', [
            'company' => $company,
            'search' => $search,
            'selectedClasses' => $selectedClasses,
            'productClasses' => $company->productClasses()->where('is_active', true)->orderBy('name')->get(),
            'products' => $company->products()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('description', 'like', "%{$search}%")
                            ->orWhere('class', 'like', "%{$search}%");
                    });
                })
                ->when($selectedClasses !== [], fn ($query) => $query->whereIn('class', $selectedClasses))
                ->orderBy('description')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $this->validated($request);
        $data['company_id'] = $company->id;
        $data['is_active'] = true;

        $company->products()->create($data);

        return redirect()->route('produtos.index')->with('status', 'Produto salvo.');
    }

    public function update(Request $request, Product $produto): RedirectResponse
    {
        $this->abortUnlessCompanyProduct($produto);
        $produto->update($this->validated($request));

        return redirect()->route('produtos.index')->with('status', 'Produto atualizado.');
    }

    public function destroy(Product $produto): RedirectResponse
    {
        $this->abortUnlessCompanyProduct($produto);
        $produto->update(['is_active' => ! $produto->is_active]);

        return redirect()->route('produtos.index')->with('status', 'Status do produto atualizado.');
    }

    public function import(Request $request): RedirectResponse
    {
        $company = $this->company();
        $request->validate(['planilha' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);
        $sheet = IOFactory::load($request->file('planilha')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headers = array_map(fn ($value) => mb_strtolower(trim((string) $value)), array_shift($rows) ?: []);
        $map = $this->headerMap($headers);
        $count = 0;

        foreach ($rows as $row) {
            $description = trim((string) ($row[$map['descricao'] ?? ''] ?? ''));
            if ($description === '') {
                continue;
            }

            $ean = preg_replace('/\D+/', '', (string) ($row[$map['ean'] ?? ''] ?? '')) ?: null;
            $payload = [
                    'description' => $description,
                    'ean' => $ean,
                    'group' => trim((string) ($row[$map['grupo'] ?? ''] ?? '')) ?: null,
                    'class' => trim((string) ($row[$map['classe'] ?? ''] ?? '')) ?: null,
                    'last_purchase_price' => $this->money($row[$map['ultimo_valor_de_compra'] ?? ''] ?? 0),
                    'is_active' => true,
            ];

            if ($ean) {
                $company->products()->updateOrCreate(['ean' => $ean], $payload);
            } else {
                $company->products()->create($payload);
            }
            $count++;
        }

        return redirect()->route('produtos.index')->with('status', "{$count} produto(s) importado(s).");
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Descricao', 'EAN', 'Grupo', 'Classe', 'Ultimo Valor de Compra'], null, 'A1');

        return $this->downloadSpreadsheet($spreadsheet, 'modelo-produtos.xlsx');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'last_purchase_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $column => $label) {
            $key = str_replace([' ', '-', '/', '.'], '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) ?: $label);
            $map[$key] = $column;
        }

        return $map;
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string) $value));
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function abortUnlessCompanyProduct(Product $product): void
    {
        abort_unless($product->company_id === $this->company()->id, 404);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
