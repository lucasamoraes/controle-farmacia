<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class BoletoSpreadsheetImporter
{
    public function import(Company $company, string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('BOLETOS') ?? $spreadsheet->getActiveSheet();
        $columns = $this->columns($sheet);
        $defaultCategory = $this->stockCategory($company);

        $stats = ['created' => 0, 'skipped' => 0, 'suppliers_created' => 0, 'suppliers_linked' => 0, 'errors' => []];

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowIndex = $row->getRowIndex();
            $dueDate = $this->dateValue($this->cell($sheet, $columns['due_date'], $rowIndex));
            $supplierName = $this->cleanText($this->cell($sheet, $columns['supplier'], $rowIndex));
            $supplierDocument = $this->documentDigits($this->cell($sheet, $columns['cnpj'], $rowIndex));
            $paymentAccount = $this->cleanText($this->cell($sheet, $columns['payment_account'], $rowIndex));
            $categoryName = $this->cleanText($this->cell($sheet, $columns['category'], $rowIndex));
            $amount = $this->moneyValue($this->cell($sheet, $columns['amount'], $rowIndex, true));
            $documentNumber = $this->documentValue($this->cell($sheet, $columns['document_number'], $rowIndex, true));

            if (! $dueDate && ! $supplierName && ! $amount) {
                continue;
            }

            if (! $dueDate || ! $supplierName || $amount <= 0) {
                $stats['skipped']++;
                $stats['errors'][] = "Linha {$rowIndex}: data, fornecedor ou valor ausente.";
                continue;
            }

            $rowCategory = $categoryName ? $this->categoryByName($company, $categoryName) : $defaultCategory;
            $supplier = $this->supplierForRow($company, $supplierName, $supplierDocument, $rowCategory->id);

            if ($supplier->wasRecentlyCreated) {
                $stats['suppliers_created']++;
            } elseif ($supplierDocument && ! $supplier->document) {
                $supplier->update(['document' => $supplierDocument]);
                $stats['suppliers_linked']++;
            }

            $exists = $company->payables()
                ->where('supplier_id', $supplier->id)
                ->whereDate('due_date', $dueDate)
                ->where('amount', $amount)
                ->when($documentNumber, fn ($query) => $query->where('document_number', $documentNumber))
                ->exists();

            if ($exists) {
                $stats['skipped']++;
                continue;
            }

            $company->payables()->create([
                'supplier_id' => $supplier->id,
                'financial_category_id' => $rowCategory->id,
                'description' => 'Boleto importado - ' . $supplier->name,
                'amount' => $amount,
                'due_date' => $dueDate,
                'status' => 'open',
                'source' => 'spreadsheet_import',
                'document_number' => $documentNumber,
                'notes' => $paymentAccount ? 'Conta de pagamento: ' . $paymentAccount : null,
            ]);

            $stats['created']++;
        }

        return $stats;
    }

    private function columns($sheet): array
    {
        $headers = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = $this->normalizeHeader($sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . '1')->getValue());
            if ($header !== '') {
                $headers[$header] ??= $column;
            }
        }

        return [
            'due_date' => $this->findColumn($headers, ['data de vencimento', 'vencimento']) ?? 1,
            'supplier' => $this->findColumn($headers, ['fornecedor', 'cedente']) ?? 3,
            'cnpj' => $this->findColumn($headers, ['cnpj', 'cnpj fornecedor', 'documento']),
            'payment_account' => $this->findColumn($headers, ['conta de pagamento', 'conta pagamento']) ?? 4,
            'category' => $this->findColumn($headers, ['categoria']) ?? 5,
            'amount' => $this->findColumn($headers, ['valor']) ?? 6,
            'document_number' => $this->findColumn($headers, ['nota fiscal', 'nf', 'documento nota']) ?? 7,
        ];
    }

    private function findColumn(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            if (isset($headers[$name])) {
                return $headers[$name];
            }
        }

        return null;
    }

    private function normalizeHeader(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $converted !== false ? $converted : $text;
        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function cell($sheet, ?int $column, int $row, bool $calculated = false): mixed
    {
        if (! $column) {
            return null;
        }

        $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row);
        return $calculated ? $cell->getCalculatedValue() : $cell->getValue();
    }

    private function stockCategory(Company $company)
    {
        return $this->categoryByName($company, 'Compra de mercadoria');
    }

    private function categoryByName(Company $company, string $name)
    {
        return $company->categories()->firstOrCreate(['name' => $name, 'type' => 'expense'], ['is_default' => true, 'is_active' => true]);
    }

    private function supplierForRow(Company $company, string $name, ?string $document, ?int $categoryId)
    {
        if ($document) {
            $existing = $company->suppliers()->where('document', $document)->first();
            if ($existing) {
                return $existing;
            }
        }

        return $company->suppliers()->firstOrCreate(['name' => $name], [
            'trade_name' => $name,
            'document' => $document,
            'financial_category_id' => $categoryId,
            'is_active' => true,
        ]);
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            if (is_numeric($value)) return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->toDateString();
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function moneyValue(mixed $value): float
    {
        if ($value === null || $value === '') return 0;
        if (is_numeric($value)) return round((float) $value, 2);
        $normalized = str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,\.\-]/', '', (string) $value) ?? ''));
        return round((float) $normalized, 2);
    }

    private function documentDigits(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 15 && str_starts_with($digits, '0')) $digits = substr($digits, 1);
        return strlen($digits) === 14 ? $digits : null;
    }

    private function documentValue(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return number_format((float) $value, 0, '', '');
        return mb_substr(trim((string) $value), 0, 255);
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        return $text !== '' ? mb_substr($text, 0, 255) : null;
    }
}

