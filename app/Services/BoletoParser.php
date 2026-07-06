<?php

namespace App\Services;

use Carbon\Carbon;

class BoletoParser
{
    public function parseText(string $text): array
    {
        $normalized = $this->normalize($text);
        $beneficiary = $this->extractBeneficiary($normalized);

        return [
            'digitable_line' => $this->extractDigitableLine($normalized),
            'document' => $beneficiary['document'] ?? $this->extractDocument($normalized),
            'due_date' => $this->extractDueDate($normalized),
            'amount' => $this->extractAmount($normalized),
            'beneficiary_name' => $beneficiary['name'] ?? $this->extractBeneficiaryName($normalized),
        ];
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractDigitableLine(string $text): ?string
    {
        preg_match_all('/(?:\d[ .-]?){44,54}/', $text, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate);

            if ($digits && strlen($digits) >= 44 && strlen($digits) <= 54) {
                return $digits;
            }
        }

        return null;
    }

    private function extractBeneficiary(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

        foreach ($lines as $index => $line) {
            if (! $this->isCedenteLine($line)) {
                continue;
            }

            $context = trim(implode(' ', array_slice($lines, $index, 3)));
            $document = $this->extractDocumentFromText($context);
            $name = $this->extractNameFromCedenteContext($context);

            if ($document || $name) {
                return [
                    'document' => $document,
                    'name' => $name,
                ];
            }
        }

        return [];
    }

    private function isCedenteLine(string $line): bool
    {
        $lower = mb_strtolower($line);

        if (str_contains($lower, 'codigo cedente') || str_contains($lower, 'responsabilidade do cedente')) {
            return false;
        }

        return preg_match('/(^|\s)(cedente|beneficiario|beneficiario final)(\s|:|$)/i', $this->stripAccents($line)) === 1;
    }

    private function extractNameFromCedenteContext(string $context): ?string
    {
        $withoutLabel = preg_replace('/.*?(?:cedente|beneficiario|beneficiario final)\s*:?\s*/i', '', $this->stripAccents($context)) ?? $context;
        $withoutDocument = preg_replace('/\s*(?:cnpj|cpf|cnpj\/cpf)\s*:?\s*[\d\.\/\-]+.*/i', '', $withoutLabel) ?? $withoutLabel;
        $name = trim($withoutDocument);

        if ($name === '' || preg_match('/^\d/', $name)) {
            return null;
        }

        return mb_substr($name, 0, 255);
    }

    private function extractDocument(string $text): ?string
    {
        return $this->extractDocumentFromText($text);
    }

    private function extractDocumentFromText(string $text): ?string
    {
        if (preg_match('/\d{2,3}\.?\d{3}\.?\d{3}[\/.]?\d{4}[-\/.]?\d{2}/', $text, $match)) {
            return $this->normalizeCnpj($match[0]);
        }

        if (preg_match('/\d{3}\.?\d{3}\.?\d{3}-?\d{2}/', $text, $match)) {
            return preg_replace('/\D+/', '', $match[0]);
        }

        return null;
    }

    private function normalizeCnpj(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) === 15 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 14 ? $digits : null;
    }

    private function extractDueDate(string $text): ?string
    {
        $datePattern = '(\d{2}[\/.\-]\d{2}[\/.\-]\d{4})';
        $patterns = [
            '/vencimento\D{0,80}' . $datePattern . '/i',
            '/venc\.\D{0,80}' . $datePattern . '/i',
            '/' . $datePattern . '\D{0,80}vencimento/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->parseBrazilianDate($match[1]);
            }
        }

        preg_match_all('/' . $datePattern . '/', $text, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $date = $this->parseBrazilianDate($candidate);

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function parseBrazilianDate(string $value): ?string
    {
        $normalized = str_replace(['.', '-'], '/', $value);

        try {
            $date = Carbon::createFromFormat('!d/m/Y', $normalized);
        } catch (\Throwable) {
            return null;
        }

        if ($date->format('d/m/Y') !== $normalized || (int) $date->format('Y') < 2000) {
            return null;
        }

        return $date->toDateString();
    }

    private function extractAmount(string $text): ?string
    {
        $patterns = [
            '/(?:valor\s*(?:do\s*documento)?|valor\s*cobrado)\D{0,40}(\d{1,3}(?:\.\d{3})*,\d{2})/i',
            '/R\$\s*(\d{1,3}(?:\.\d{3})*,\d{2})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->decimalFromBrazilian($match[1]);
            }
        }

        return null;
    }

    private function extractBeneficiaryName(string $text): ?string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

        foreach ($lines as $index => $line) {
            if ($this->isCedenteLine($line)) {
                $context = trim(implode(' ', array_slice($lines, $index, 2)));
                $name = $this->extractNameFromCedenteContext($context);

                if ($name) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function stripAccents(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $converted !== false ? $converted : $value;
    }

    private function decimalFromBrazilian(string $value): string
    {
        return str_replace(',', '.', str_replace('.', '', $value));
    }
}
