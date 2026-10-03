<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MarketingImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MarketingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_import_meta_ads_spreadsheet_without_duplicating_same_period(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        $path = $this->metaAdsSpreadsheet();

        $this->actingAs($owner)->post('/marketing/importar', [
            'spreadsheet' => new UploadedFile($path, 'meta-ads.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect('/marketing/importar');

        $this->assertDatabaseHas('marketing_campaign_metrics', [
            'company_id' => $company->id,
            'campaign_name' => 'Campanha Mensagens 01',
            'amount_spent' => 397.96,
            'reach' => 14619,
            'link_clicks' => 188,
        ]);

        $secondPath = $this->metaAdsSpreadsheet(420.50);
        $this->actingAs($owner)->post('/marketing/importar', [
            'spreadsheet' => new UploadedFile($secondPath, 'meta-ads-atualizado.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect('/marketing/importar');

        $this->assertDatabaseCount('marketing_campaign_metrics', 1);
        $this->assertDatabaseHas('marketing_campaign_metrics', ['amount_spent' => 420.50]);
    }

    public function test_viewer_can_open_report_but_cannot_import_data(): void
    {
        [$company, $viewer] = $this->companyWithUser('viewer');
        $this->metric($company);

        $this->actingAs($viewer)->get('/relatorios/marketing')
            ->assertOk()
            ->assertSee('Investimento e eficiencia por mes')
            ->assertSee('Campeas do periodo')
            ->assertSee('Campanha Mensagens 01');

        $this->actingAs($viewer)->get('/marketing/importar')->assertForbidden();
    }

    public function test_ai_analysis_uses_filtered_aggregates_and_returns_response(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        $this->metric($company);
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'test-model');
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [['type' => 'output_text', 'text' => 'Revise primeiro a campanha com CPR mais alto.']],
                ]],
            ]),
        ]);

        $this->actingAs($owner)->post('/relatorios/marketing/analisar', [
            'question' => 'Qual campanha devo revisar?',
            'start' => '2026-04-01',
            'end' => '2026-04-30',
        ])->assertRedirect('/relatorios/marketing?start=2026-04-01&end=2026-04-30')
            ->assertSessionHas('marketing_ai_analysis', 'Revise primeiro a campanha com CPR mais alto.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false
            && str_contains($request['input'], 'Campanha Mensagens 01')
            && str_contains($request['input'], 'Inativa')
            && str_contains($request['instructions'], 'Nunca recomende pausar uma campanha inativa'));
    }

    public function test_report_can_filter_active_campaigns(): void
    {
        [$company, $owner] = $this->companyWithUser('owner');
        $this->metric($company);
        $this->metric($company, 'Campanha Ativa', 'active', 'active-metric', 200);

        $this->actingAs($owner)->get('/relatorios/marketing?status=active')
            ->assertOk()
            ->assertSee('Campanha Ativa')
            ->assertSee('R$ 200,00')
            ->assertDontSee('R$ 397,96');
    }

    private function metaAdsSpreadsheet(float $spent = 397.96): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'Inicio dos relatorios', 'Encerramento dos relatorios', 'Nome do conjunto de anuncios',
            'Veiculacao do conjunto de anuncios', 'Configuracao de atribuicao', 'Resultados',
            'Indicador de resultados', 'Alcance', 'Frequencia', 'Custo por resultados',
            'Valor usado (BRL)', 'Impressoes', 'CPM (custo por 1.000 impressoes) (BRL)',
            'Cliques no link', 'CPC (custo por clique no link) (BRL)', 'CTR (taxa de cliques no link)',
            'Cliques (todos)', 'Visualizacoes da pagina de destino',
        ], null, 'A1');
        $sheet->fromArray([
            '01/04/2026', '30/04/2026', 'Campanha Mensagens 01', 'inactive',
            'Clique de 7 dias', 79, 'mensagens', 14619, 2.06, 5.04, $spent,
            30149, 13.20, 188, 2.12, 0.62, 343, 25,
        ], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'meta-ads-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function metric(
        Company $company,
        string $name = 'Campanha Mensagens 01',
        string $status = 'inactive',
        string $fingerprint = 'metric',
        float $spent = 397.96,
    ): void {
        $import = MarketingImport::create([
            'company_id' => $company->id,
            'original_filename' => 'meta.xlsx',
        ]);
        $company->marketingCampaignMetrics()->create([
            'marketing_import_id' => $import->id,
            'fingerprint' => hash('sha256', $fingerprint),
            'report_start' => '2026-04-01',
            'report_end' => '2026-04-30',
            'campaign_name' => $name,
            'delivery_status' => $status,
            'results' => 79,
            'reach' => 14619,
            'impressions' => 30149,
            'link_clicks' => 188,
            'all_clicks' => 343,
            'frequency' => 2.06,
            'amount_spent' => $spent,
            'cpc' => 2.12,
            'cpm' => 13.20,
            'ctr' => 0.62,
            'cost_per_result' => 5.04,
        ]);
    }

    private function companyWithUser(string $role): array
    {
        $company = Company::create(['name' => 'Farmacia Teste', 'trade_name' => 'Farmacia Teste']);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['role' => $role]);

        return [$company, $user];
    }
}
