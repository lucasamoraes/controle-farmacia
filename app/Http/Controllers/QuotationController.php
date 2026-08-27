<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PurchaseList;
use App\Models\Quotation;
use App\Models\QuotationSupplier;
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
        $cotacao->load(['purchaseList.items.product', 'participants.supplier', 'prices']);
        $participants = $cotacao->participants
            ->sortBy(fn ($participant) => $participant->quoted_name)
            ->values();

        return view('quotations.show', [
            'company' => $company,
            'quotation' => $cotacao,
            'list' => $cotacao->purchaseList,
            'participants' => $participants,
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
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);
        $supplier = $this->merchandiseSuppliers($company)->findOrFail($data['supplier_id']);
        $displayName = trim((string) ($data['display_name'] ?? ''));

        if ($displayName === '') {
            $existing = $cotacao->participants()->where('supplier_id', $supplier->id)->whereNull('display_name')->first();
            if ($existing) {
                return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Fornecedor ja estava na cotacao.');
            }
        }

        $cotacao->participants()->create([
            'supplier_id' => $supplier->id,
            'display_name' => $displayName !== '' ? $displayName : null,
        ]);

        return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Fornecedor adicionado a cotacao.');
    }

    public function removeSupplier(Quotation $cotacao, QuotationSupplier $participante): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless(Auth::user()->canWriteFinance($this->company()), 403);
        abort_unless($participante->quotation_id === $cotacao->id, 404);

        $cotacao->prices()->where('quotation_supplier_id', $participante->id)->delete();
        $participante->delete();

        return redirect()->route('cotacoes.show', $cotacao)->with('status', 'Fornecedor removido da cotacao.');
    }

    public function updatePrices(Request $request, Quotation $cotacao): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless(Auth::user()->canWriteFinance($this->company()), 403);
        $prices = $request->input('prices', []);
        $quantities = $request->input('quantities', []);
        $selectedWinners = $request->input('selected_winners', []);

        foreach ($quantities as $itemId => $quantity) {
            $item = $cotacao->purchaseList->items()->whereKey($itemId)->first();
            if (! $item) {
                continue;
            }

            $quantity = (float) str_replace(',', '.', (string) $quantity);
            if ($quantity > 0) {
                $item->update(['quantity' => $quantity]);
            }
        }

        foreach ($prices as $itemId => $supplierPrices) {
            foreach ($supplierPrices as $participantId => $value) {
                $participant = $cotacao->participants()->whereKey($participantId)->first();
                if (! $participant) {
                    continue;
                }

                $value = $this->money($value);
                if ($value <= 0) {
                    $cotacao->prices()
                        ->where('purchase_list_item_id', $itemId)
                        ->where('quotation_supplier_id', $participant->id)
                        ->delete();
                    continue;
                }

                $cotacao->prices()->updateOrCreate([
                    'purchase_list_item_id' => $itemId,
                    'quotation_supplier_id' => $participant->id,
                ], [
                    'supplier_id' => $participant->supplier_id,
                    'unit_price' => $value,
                    'is_selected_winner' => (string) ($selectedWinners[$itemId] ?? '') === (string) $participant->id,
                ]);
            }

            if (! array_key_exists($itemId, $selectedWinners)) {
                continue;
            }

            $cotacao->prices()
                ->where('purchase_list_item_id', $itemId)
                ->where('quotation_supplier_id', '!=', $selectedWinners[$itemId])
                ->update(['is_selected_winner' => false]);
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

    public function importSupplierPrices(Request $request, Quotation $cotacao, QuotationSupplier $participante): RedirectResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        abort_unless($participante->quotation_id === $cotacao->id, 404);
        abort_unless($participante->supplier?->company_id === $this->company()->id, 404);
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
                'quotation_supplier_id' => $participante->id,
            ], [
                'supplier_id' => $participante->supplier_id,
                'unit_price' => $price,
            ]);
            $count++;
        }

        return redirect()->route('cotacoes.show', $cotacao)->with('status', "{$count} preco(s) importado(s) para {$participante->quoted_name}.");
    }

    public function exportWinnerOrder(Quotation $cotacao, QuotationSupplier $participante): StreamedResponse
    {
        $this->abortUnlessCompanyQuotation($cotacao);
        $rows = $this->winnerRowsForParticipant($cotacao, $participante);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pedido');
        $sheet->fromArray(['Fornecedor', $participante->quoted_name, 'Data', now()->format('d/m/Y')], null, 'A1');
        $sheet->fromArray(['Descricao', 'Quantidade', 'Unidade', 'Valor unitario', 'Total'], null, 'A3');
        $line = 4;
        foreach ($rows as $row) {
            $sheet->fromArray([$row['item']->description, (float) $row['item']->quantity, $row['item']->unit, $row['price'], $row['total']], null, "A{$line}");
            $line++;
        }

        return $this->downloadSpreadsheet($spreadsheet, 'pedido-'.$participante->id.'-cotacao-'.$cotacao->id.'.xlsx');
    }

    public function printWinnerOrder(Quotation $cotacao, QuotationSupplier $participante)
    {
        $this->abortUnlessCompanyQuotation($cotacao);

        return view('quotations.order-print', [
            'company' => $this->company(),
            'quotation' => $cotacao,
            'supplierName' => $participante->quoted_name,
            'supplier' => $participante->supplier,
            'rows' => $this->winnerRowsForParticipant($cotacao, $participante),
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
            ->map(fn ($rows) => $rows->keyBy('quotation_supplier_id'))
            ->all();
    }

    private function winners(Quotation $quotation): array
    {
        $prices = $this->matrix($quotation);
        $winners = [];

        foreach ($quotation->purchaseList->items as $item) {
            $availablePrices = collect($prices[$item->id] ?? [])
                ->filter(fn ($price) => (float) $price->unit_price > 0)
                ->sortBy('unit_price');
            $lowest = $availablePrices->first();
            $selected = $availablePrices->first(fn ($price) => (bool) $price->is_selected_winner);
            $winner = $selected ?: $lowest;
            if ($winner) {
                $last = (float) ($item->product?->last_purchase_price ?? 0);
                $unit = (float) $winner->unit_price;
                $winners[$item->id] = [
                    'quotation_supplier_id' => $winner->quotation_supplier_id,
                    'supplier_id' => $winner->supplier_id,
                    'unit_price' => $unit,
                    'lowest_quotation_supplier_id' => $lowest?->quotation_supplier_id,
                    'lowest_unit_price' => $lowest ? (float) $lowest->unit_price : null,
                    'manual' => (bool) $selected,
                    'variation' => $last > 0 ? (($unit - $last) / $last) * 100 : null,
                ];
            }
        }

        return $winners;
    }

    private function winnerRowsForParticipant(Quotation $quotation, QuotationSupplier $participant): array
    {
        abort_unless($participant->quotation_id === $quotation->id, 404);
        abort_unless($participant->supplier?->company_id === $this->company()->id, 404);
        $quotation->loadMissing(['purchaseList.items.product', 'prices']);
        $winners = $this->winners($quotation);
        $rows = [];

        foreach ($quotation->purchaseList->items as $item) {
            $winner = $winners[$item->id] ?? null;
            if (! $winner || (int) $winner['quotation_supplier_id'] !== (int) $participant->id) {
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
