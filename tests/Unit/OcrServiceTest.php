<?php

namespace Tests\Unit;

use App\Services\OcrService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    public function test_it_extracts_text_with_ocrspace(): void
    {
        config([
            'services.ocr.provider' => 'ocrspace',
            'services.ocr.api_key' => 'test-key',
            'services.ocr.endpoint' => 'https://api.ocr.space/parse/image',
        ]);

        Http::fake([
            'api.ocr.space/*' => Http::response([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    ['ParsedText' => "Cedente Teste\nVencimento 22/07/2026"],
                ],
            ]),
        ]);

        $file = tempnam(sys_get_temp_dir(), 'ocr');
        file_put_contents($file, 'fake pdf content');

        $text = app(OcrService::class)->extractText($file);

        $this->assertStringContainsString('Cedente Teste', $text);
        $this->assertStringContainsString('Vencimento 22/07/2026', $text);
    }

    public function test_it_fails_when_ocr_is_disabled(): void
    {
        config(['services.ocr.provider' => 'disabled']);

        $this->expectException(RuntimeException::class);

        app(OcrService::class)->extractText(__FILE__);
    }
}
