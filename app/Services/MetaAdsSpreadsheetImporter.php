<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MarketingImport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class MetaAdsSpreadsheetImporter
{
    public function import(Company $company, MarketingImport $import, string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        [$columns, $headers] = $this->columns($sheet);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $periodStarts = [];
        $periodEnds = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowIndex = $row->getRowIndex();
            $campaignName = $this->text($this->cell($sheet, $columns['campaign'], $rowIndex));
            $reportStart = $this->date($this->cell($sheet, $columns['report_start'], $rowIndex));
            $reportEnd = $this->date($this->cell($sheet, $columns['report_end'], $rowIndex));

            if ($campaignName === '' && ! $reportStart && ! $reportEnd) {
                continue;
            }

            if ($campaignName === '' || ! $reportStart || ! $reportEnd) {
                $stats['skipped']++;
                $stats['errors'][] = "Linha {$rowIndex}: periodo ou nome da campanha ausente.";

                continue;
            }

            $results = $this->number($this->cell($sheet, $columns['results'], $rowIndex));
            $spent = $this->number($this->cell($sheet, $columns['amount_spent'], $rowIndex));
            $reach = (int) round($this->number($this->cell($sheet, $columns['reach'], $rowIndex)));
            $impressions = (int) round($this->number($this->cell($sheet, $columns['impressions'], $rowIndex)));
            $linkClicks = (int) round($this->number($this->cell($sheet, $columns['link_clicks'], $rowIndex)));
            $allClicks = (int) round($this->number($this->cell($sheet, $columns['all_clicks'], $rowIndex)));
            $resultIndicator = $this->text($this->cell($sheet, $columns['result_indicator'], $rowIndex));
            $fingerprint = hash('sha256', implode('|', [
                mb_strtolower($campaignName),
                $reportStart,
                $reportEnd,
                mb_strtolower($resultIndicator),
            ]));

            $rawData = [];
            foreach ($headers as $column => $header) {
                $rawData[$header] = $this->cell($sheet, $column, $rowIndex, true);
            }

            $payload = [
                'marketing_import_id' => $import->id,
                'report_start' => $reportStart,
                'report_end' => $reportEnd,
                'campaign_name' => mb_substr($campaignName, 0, 255),
                'delivery_status' => $this->limitedText($this->cell($sheet, $columns['delivery_status'], $rowIndex)),
                'attribution_setting' => $this->limitedText($this->cell($sheet, $columns['attribution'], $rowIndex)),
                'results' => round($results, 4),
                'result_indicator' => $this->limitedText($this->cell($sheet, $columns['result_indicator'], $rowIndex)),
                'reach' => max(0, $reach),
                'impressions' => max(0, $impressions),
                'link_clicks' => max(0, $linkClicks),
                'all_clicks' => max(0, $allClicks),
                'landing_page_views' => max(0, (int) round($this->number($this->cell($sheet, $columns['landing_page_views'], $rowIndex)))),
                'frequency' => round($this->number($this->cell($sheet, $columns['frequency'], $rowIndex)), 6),
                'amount_spent' => round($spent, 2),
                'cpc' => round($this->metricOrFallback($sheet, $columns['cpc'], $rowIndex, $linkClicks > 0 ? $spent / $linkClicks : 0), 6),
                'cpm' => round($this->metricOrFallback($sheet, $columns['cpm'], $rowIndex, $impressions > 0 ? ($spent / $impressions) * 1000 : 0), 6),
                'ctr' => round($this->metricOrFallback($sheet, $columns['ctr'], $rowIndex, $impressions > 0 ? ($linkClicks / $impressions) * 100 : 0), 6),
                'cost_per_result' => round($this->metricOrFallback($sheet, $columns['cost_per_result'], $rowIndex, $results > 0 ? $spent / $results : 0), 6),
                'raw_data' => $rawData,
            ];

            $metric = $company->marketingCampaignMetrics()->where('fingerprint', $fingerprint)->first();
            if ($metric) {
                $metric->update($payload);
                $stats['updated']++;
            } else {
                $company->marketingCampaignMetrics()->create($payload + ['fingerprint' => $fingerprint]);
                $stats['created']++;
            }

            $periodStarts[] = $reportStart;
            $periodEnds[] = $reportEnd;
        }

        if ($stats['created'] + $stats['updated'] === 0) {
            throw new RuntimeException('Nenhuma linha valida do Meta Ads foi encontrada na planilha.');
        }

        $import->update([
            'rows_created' => $stats['created'],
            'rows_updated' => $stats['updated'],
            'rows_skipped' => $stats['skipped'],
            'period_start' => min($periodStarts),
            'period_end' => max($periodEnds),
        ]);

        return $stats;
    }

    private function columns($sheet): array
    {
        $normalized = [];
        $headers = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($column = 1; $column <= $highestColumn; $column++) {
            $original = trim((string) $this->cell($sheet, $column, 1));
            if ($original === '') {
                continue;
            }
            $headers[$column] = $original;
            $normalized[$this->normalize($original)] = $column;
        }

        $columns = [
            'report_start' => $this->find($normalized, ['inicio dos relatorios', 'reporting starts']),
            'report_end' => $this->find($normalized, ['encerramento dos relatorios', 'reporting ends']),
            'campaign' => $this->find($normalized, ['nome do conjunto de anuncios', 'nome da campanha', 'ad set name', 'campaign name']),
            'delivery_status' => $this->find($normalized, ['veiculacao do conjunto de anuncios', 'delivery']),
            'attribution' => $this->find($normalized, ['configuracao de atribuicao', 'attribution setting']),
            'results' => $this->find($normalized, ['resultados', 'results']),
            'result_indicator' => $this->find($normalized, ['indicador de resultados', 'result indicator']),
            'reach' => $this->find($normalized, ['alcance', 'reach']),
            'frequency' => $this->find($normalized, ['frequencia', 'frequency']),
            'cost_per_result' => $this->find($normalized, ['custo por resultados', 'custo por resultado', 'cost per result']),
            'amount_spent' => $this->find($normalized, ['valor usado (brl)', 'valor usado', 'amount spent (brl)', 'amount spent']),
            'impressions' => $this->find($normalized, ['impressoes', 'impressions']),
            'cpm' => $this->findStartsWith($normalized, ['cpm (custo por 1.000 impressoes)', 'cpm']),
            'link_clicks' => $this->find($normalized, ['cliques no link', 'link clicks']),
            'cpc' => $this->findStartsWith($normalized, ['cpc (custo por clique no link)', 'cpc (cost per link click)']),
            'ctr' => $this->findStartsWith($normalized, ['ctr (taxa de cliques no link)', 'ctr (link click-through rate)']),
            'all_clicks' => $this->find($normalized, ['cliques (todos)', 'clicks (all)']),
            'landing_page_views' => $this->find($normalized, ['visualizacoes da pagina de destino', 'landing page views']),
        ];

        foreach (['report_start', 'report_end', 'campaign', 'amount_spent'] as $required) {
            if (! $columns[$required]) {
                throw new RuntimeException('A planilha nao possui todas as colunas obrigatorias do Meta Ads.');
            }
        }

        return [$columns, $headers];
    }

    private function find(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            if (isset($headers[$name])) {
                return $headers[$name];
            }
        }

        return null;
    }

    private function findStartsWith(array $headers, array $prefixes): ?int
    {
        foreach ($headers as $name => $column) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function normalize(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $converted !== false ? $converted : $text;

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function cell($sheet, ?int $column, int $row, bool $formatted = false): mixed
    {
        if (! $column) {
            return null;
        }

        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);

        return $formatted ? $cell->getFormattedValue() : $cell->getCalculatedValue();
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = preg_replace('/[^0-9,.-]/', '', trim((string) $value)) ?? '';
        if ($text === '' || $text === '-') {
            return 0;
        }
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
        }
        $text = str_replace(',', '.', $text);

        return is_numeric($text) ? (float) $text : 0;
    }

    private function date(mixed $value): ?string
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            $text = trim((string) $value);
            foreach (['d/m/Y', 'Y-m-d', 'm/d/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->toDateString();
                } catch (Throwable) {
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function limitedText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function metricOrFallback($sheet, ?int $column, int $row, float $fallback): float
    {
        $value = $this->number($this->cell($sheet, $column, $row));

        return $value > 0 ? $value : $fallback;
    }
}
