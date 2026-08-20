<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PurchaseList;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationController extends Controller
{
    public function start(PurchaseList $lista): RedirectResponse
    {
        $this->abortUnlessCompanyList($lista);
        $company = $this->company();
        abort_unless(Auth::user()->canWriteFinance($company), 403);
        abort_if($lista->items()->count() === 0, 422, 'Adicione produtos antes de iniciar a cotacao.');

        $quotation = $lista->quotation()->firstOrCreate([
            'company_id' => $company->id,
        ], [
            'created_by' => Auth::id(),
            'status' => 'open',
            'quoted_at' => now(),
        ]);
        $lista->update([
            'status' => 'quoting',
            'started_quotation_at' => $lista->started_quotation_at ?: now(),
        ]);

        return redirect()->route('cotacoes.show', $quotation)->with('status', 'Cotacao iniciada.');
    }

    public function show(Quotation $cotacao)
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        $company = $this->company();
        $cotacao->load(['purchaseList.items.product', 'suppliers', 'prices']);

        return view('quotations.show', [
            'company' => $company,
            'quotation' => $cotacao,
            'list' => $cotacao->purchaseList,
            'suppliers' => $cotacao->suppliers()->orderBy('name')->get(),
            'availableSuppliers' => $this->merchandiseSuppliers($company)->get(),
            'matrix' => $this->matrix($cotacao),
            'winners' => $this->winners($cotacao),
        ]);
    }

    public function addSupplier(Request $request, Quotation $cotacao): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        $company = $this->company();
        abort_unless(Auth::user()->canWriteFinance($company), 403);
        $data = $request->validate(['supplier_id' => ['required', 'exists:suppliers,id']]);
        $supplier = $this->merchandiseSuppliers($company)->findOrFail($data['supplier_id']);
        $cotacao->suppliers()->syncWithoutDetaching([$supplier->id]);

        return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Fornecedor adicionado a cotacao.');
    }

    public function updatePrices(Request $request, Quotation $cotacao): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless(Auth::user()->canWriteFinance($this->company()), 403);
        $prices = $request->input('prices', []);

        foreach ($prices as $itemId => $supplierPrices) {
            foreach ($supplierPrices as $supplierId => $value) {
                $value = $this->money($value);
                if ($value <= 0) {
                    $cotacao->prices()
                        ->where('purchase_list_item_id', $itemId)
                        ->where('supplier_id', $supplierId)
                        ->delete();
                    continue;
                }

                $cotacao->prices()->updateOrCreate([
                    'purchase_list_item_id' => $itemId,
                    'supplier_id' => $supplierId,
                ], ['unit_price' => $value]);
            }
        }

        return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Precos atualizados.');
    }

    public function exportList(Quotation $cotacao): StreamedResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produtos');
        $sheet->fromArray(['Descricao', 'Quantidade', 'Preco'], null, 'A1');
        $row = 2;
        foreach ($cotacao->purchaseList->items()->orderBy('description')->get() as $item) {
            $sheet->fromArray([$item->description, (float) $item->quantity, null], null, "A{$row}");
            $row++;
        }

        return $this->downloadSpreadsheet($spreadsheet, 'cotacao-produtos-'.$cotacao->id.'.xlsx');
    }

    public function importSupplierPrices(Request $request, Quotation $cotacao, Supplier $fornecedor): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless($fornecedor->company_id === $this->company()->id, 404);
        abort_unless($cotacao->suppliers()->whereKey($fornecedor->id)->exists(), 403);
        $request->validate(['planilha' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);
        $rows = IOFactory::load($request->file('planilha')->getRealPath())->getActiveSheet()->toArray(null, true, true, true);
        $headers = array_map(fn ($value) => mb_strtolower(trim((string) $value)), array_shift($rows) ?: []);
        $map = $this->headerMap($headers);
        $items = $cotacao->purchaseList->items()->get();
        $count = 0;

        foreach ($rows as $row) {
            $description = mb_strtolower(trim((string) ($row[$map['descricao'] ?? ''] ?? '')));
            $price = $this->money($row[$map['preco'] ?? $map['valor'] ?? $map['preco_unitario'] ?? ''] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $item = $items->first(fn ($candidate) => mb_strtolower($candidate->description) === $description);
            if (! $item) {
                continue;
            }

            $cotacao->prices()->updateOrCreate([
                'purchase_list_item_id' => $item->id,
                'supplier_id' => $fornecedor->id,
            ], ['unit_price' => $price]);
            $count++;
        }

        return redirect()->route('cotacoes.show', $cotacao)->with('status', "{$count} preco(s) importado(s) para {$fornecedor->name}.");
    }

    public function exportWinnerOrder(Quotation $cotacao, Supplier $fornecedor): StreamedResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        $rows = $this->winnerRowsForSupplier($cotacao, $fornecedor);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pedido');
        $sheet->fromArray(['Fornecedor', $fornecedor->name, 'Data', now()->format('d/m/Y')], null, 'A1');
        $sheet->fromArray(['Descricao', 'Quantidade', 'Unidade', 'Valor unitario', 'Total'], null, 'A3');
        $line = 4;
        foreach ($rows as $row) {
            $sheet->fromArray([$row['item']->description, (float) $row['item']->quantity, $row['item']->unit, $row['price'], $row['total']], null, "A{$line}");
            $line++;
        }

        return $this->downloadSpreadsheet($spreadsheet, 'pedido-'.$fornecedor->id.'-cotacao-'.$cotacao->id.'.xlsx');
    }

    public function printWinnerOrder(Quotation $cotacao, Supplier $fornecedor)
    {
        $this->abortUnlessCompanyQuotation($cotacao);

        return view('quotations.order-print', [
            'company' => $this->company(),
            'quotation' => $cotacao,
            'supplier' => $fornecedor,
            'rows' => $this->winnerRowsForSupplier($cotacao, $fornecedor),
        ]);
    }

    public function finalize(Quotation $cotacao): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless(Auth::user()->canWriteFinance($this->company()), 403);
        $cotacao->update(['status' => 'finalized', 'finalized_at' => now()]);
        $cotacao->purchaseList->update(['status' => 'finalized', 'finalized_at' => now()]);

        return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Cotacao finalizada.');
    }

    private function matrix(Quotation $quotation): array
    {
        return $quotation->prices
            ->groupBy('purchase_list_item_id')
            ->map(fn ($rows) => $rows->keyBy('supplier_id'))
            ->all();
    }

    private function winners(Quotation $quotation): array
    {
        $prices = $this->matrix($quotation);
        $winners = [];

        foreach ($quotation->purchaseList->items as $item) {
            $winner = collect($prices[$item->id] ?? [])
                ->filter(fn ($price) => (float) $price->unit_price > 0)
                ->sortBy('unit_price')
                ->first();
            if ($winner) {
                $last = (float) ($item->product?->last_purchase_price ?? 0);
                $unit = (float) $winner->unit_price;
                $winners[$item->id] = [
                    'supplier_id' => $winner->supplier_id,
                    'unit_price' => $unit,
                    'variation' => $last > 0 ? (($unit - $last) / $last) * 100 : null,
                ];
            }
        }

        return $winners;
    }

    private function winnerRowsForSupplier(Quotation $quotation, Supplier $supplier): array
    {
        abort_unless($supplier->company_id === $this->company()->id, 404);
        $quotation->loadMissing(['purchaseList.items.product', 'prices']);
        $winners = $this->winners($quotation);
        $rows = [];

        foreach ($quotation->purchaseList->items as $item) {
            $winner = $winners[$item->id] ?? null;
            if (! $winner || (int) $winner['supplier_id'] !== (int) $supplier->id) {
                continue;
            }
            $rows[] = [
                'item' => $item,
                'price' => $winner['unit_price'],
                'total' => $winner['unit_price'] * (float) $item->quantity,
                'variation' => $winner['variation'],
            ];
        }

        return $rows;
    }

    private function merchandiseSuppliers(Company $company)
    {
        return $company->suppliers()
            ->where('is_active', true)
            ->whereHas('category', function ($query) {
                $query->where('name', 'like', '%mercadoria%')
                    ->orWhere('name', 'like', '%estoque%')
                    ->orWhere('name', 'like', '%farmacia%');
            })
            ->orderBy('name');
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

    private function abortUnlessCompanyList(PurchaseList $list): void
    {
        abort_unless($list->company_id === $this->company()->id, 404);
    }

    private function abortUnlessCompanyQuotation(Quotation $quotation): void
    {
        abort_unless($quotation->company_id === $this->company()->id, 404);
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }
}
