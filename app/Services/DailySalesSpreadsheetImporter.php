<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class DailySalesSpreadsheetImporter
{
    public function import(Company $company, string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('VENDAS') ?? $spreadsheet->getActiveSheet();
        $columns = $this->columns($sheet);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowIndex = $row->getRowIndex();
            $saleDate = $this->dateValue($this->cell($sheet, $columns['date'], $rowIndex));
            $amount = $this->moneyValue($this->cell($sheet, $columns['amount'], $rowIndex, true));
            $weekday = $this->cleanText($this->cell($sheet, $columns['weekday'], $rowIndex));

            if (! $saleDate && $amount <= 0) {
                continue;
            }

            if (! $saleDate || $amount <= 0) {
                $stats['skipped']++;
                $stats['errors'][] = "Linha {$rowIndex}: data ou valor ausente.";
                continue;
            }

            $weekday = $weekday ?: Carbon::parse($saleDate)->locale('pt_BR')->translatedFormat('l');
            $sale = $company->dailySales()->updateOrCreate(
                ['sale_date' => $saleDate],
                ['weekday' => mb_substr($weekday, 0, 255), 'amount' => $amount]
            );

            $sale->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
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
            'date' => $this->findColumn($headers, ['data', 'data venda', 'data da venda', 'dia']) ?? 1,
            'amount' => $this->findColumn($headers, ['valor', 'valor vendido', 'faturamento', 'venda']) ?? 2,
            'weekday' => $this->findColumn($headers, ['dia da semana', 'semana', 'weekday']) ?? 3,
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

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->toDateString();
            }

            return Carbon::createFromFormat('d/m/Y', trim((string) $value))->toDateString();
        } catch (Throwable) {
            try {
                return Carbon::parse((string) $value)->toDateString();
            } catch (Throwable) {
                return null;
            }
        }
    }

    private function moneyValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,\.\-]/', '', (string) $value) ?? ''));
        return round((float) $normalized, 2);
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text !== '' ? mb_substr($text, 0, 255) : null;
    }
}
