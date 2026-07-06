<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OcrService
{
    public function isEnabled(): bool
    {
        return config('services.ocr.provider') !== 'disabled';
    }

    public function extractText(string $path): string
    {
        return match (config('services.ocr.provider')) {
            'ocrspace' => $this->extractWithOcrSpace($path),
            default => throw new RuntimeException('OCR nao configurado. Configure OCR_PROVIDER e OCR_API_KEY no ambiente de producao.'),
        };
    }

    private function extractWithOcrSpace(string $path): string
    {
        $apiKey = config('services.ocr.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OCR.space nao configurado. Informe OCR_API_KEY no arquivo .env.');
        }

        $handle = fopen($path, 'r');

        try {
            $response = Http::timeout((int) config('services.ocr.timeout', 60))
                ->attach('file', $handle, basename($path))
                ->post((string) config('services.ocr.endpoint'), [
                    'apikey' => $apiKey,
                    'language' => config('services.ocr.language', 'por'),
                    'isOverlayRequired' => 'false',
                    'detectOrientation' => 'true',
                    'scale' => 'true',
                    'OCREngine' => '2',
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException('Servico de OCR indisponivel no momento.');
        }

        $payload = $response->json();

        if (($payload['IsErroredOnProcessing'] ?? false) === true) {
            $message = $payload['ErrorMessage'] ?? $payload['ErrorDetails'] ?? 'Nao foi possivel processar o OCR.';
            throw new RuntimeException(is_array($message) ? implode(' ', $message) : $message);
        }

        $text = collect($payload['ParsedResults'] ?? [])
            ->pluck('ParsedText')
            ->filter()
            ->implode("\n");

        if (trim($text) === '') {
            throw new RuntimeException('OCR executado, mas nenhum texto foi encontrado no boleto.');
        }

        return trim($text);
    }
}
