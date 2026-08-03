<?php

namespace Tests\Feature;

use App\Models\BoletoUpload;
use App\Models\Company;
use App\Models\User;
use App\Services\BoletoParser;
use App\Services\CnpjLookupService;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoletoUploadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_boleto_upload_page(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/boletos/novo')
            ->assertOk()
            ->assertSee('Enviar boleto')
            ->assertSee('PDF ou imagem');
    }

    public function test_user_can_upload_boleto_image_for_ocr_review(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $this->mock(OcrService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')->once()->andReturn(true);
            $mock->shouldReceive('extractText')->once()->andReturn('texto lido do boleto por imagem');
        });
        $this->mock(BoletoParser::class, function ($mock) {
            $mock->shouldReceive('parseText')->once()->andReturn([
                'beneficiary_name' => 'DISTRIBUIDORA IMAGEM',
                'amount' => 123.45,
                'due_date' => '2026-08-10',
            ]);
        });
        $this->mock(CnpjLookupService::class, function ($mock) {
            $mock->shouldReceive('lookup')->once()->andReturn(null);
        });

        $response = $this->actingAs($user)->post('/boletos', [
            'boleto_pdf' => UploadedFile::fake()->create('boleto.png', 50, 'image/png'),
        ]);

        $boleto = BoletoUpload::firstOrFail();
        $response->assertRedirect("/boletos/{$boleto->id}/revisar");
        $this->assertSame('review', $boleto->processing_status);
        $this->assertTrue($boleto->parsed_data['ocr_used']);
    }
}
