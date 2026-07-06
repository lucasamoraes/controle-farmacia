<?php

namespace Tests\Feature;

use App\Models\BoletoUpload;
use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BoletoSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SupplierDocumentLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_links_cnpj_from_spreadsheet(): void
    {
        $company = Company::create(['name' => 'Farmacia Teste']);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BOLETOS');
        $sheet->fromArray(['DATA DE VENCIMENTO', 'FORNECEDOR', 'CNPJ', 'CONTA DE PAGAMENTO', 'CATEGORIA', 'VALOR', 'NOTA FISCAL'], null, 'A1');
        $sheet->fromArray(['2026-07-10', 'GRUPO SC', '01.206.820/0001-05', 'Banco do Brasil', 'Compra de mercadoria', 811.60, '123'], null, 'A2');
        $path = storage_path('framework/testing/import-cnpj.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        $stats = app(BoletoSpreadsheetImporter::class)->import($company, $path);

        $this->assertSame(1, $stats['created']);
        $this->assertDatabaseHas('suppliers', [
            'company_id' => $company->id,
            'name' => 'GRUPO SC',
            'document' => '01206820000105',
        ]);
    }

    public function test_boleto_confirmation_can_link_cnpj_to_existing_supplier(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);
        $category = FinancialCategory::create([
            'company_id' => $company->id,
            'name' => 'Compra de mercadoria',
            'type' => 'expense',
        ]);
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'GRUPO SC',
        ]);
        $boleto = BoletoUpload::create([
            'company_id' => $company->id,
            'original_file_name' => 'boleto.pdf',
            'stored_path' => 'boletos/teste.pdf',
            'processing_status' => 'review',
            'parsed_data' => [
                'document' => '01206820000105',
                'beneficiary_name' => 'SC DISTRIBUICAO LTDA',
                'cnpj_lookup' => ['document' => '01206820000105', 'name' => 'SC DISTRIBUICAO LTDA', 'legal_status' => 'ATIVA'],
            ],
        ]);

        $this->actingAs($user)->post("/boletos/{$boleto->id}/confirmar", [
            'supplier_id' => $supplier->id,
            'link_document_to_supplier' => '1',
            'financial_category_id' => $category->id,
            'description' => 'SC DISTRIBUICAO LTDA',
            'amount' => '811.60',
            'due_date' => '2026-07-22',
        ])->assertRedirect('/contas-a-pagar');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'document' => '01206820000105',
            'legal_status' => 'ATIVA',
        ]);
    }
}
