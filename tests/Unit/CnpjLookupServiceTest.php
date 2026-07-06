<?php

namespace Tests\Unit;

use App\Services\CnpjLookupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjLookupServiceTest extends TestCase
{
    public function test_it_maps_brasilapi_cnpj_data(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response([
                'cnpj' => '11.222.333/0001-44',
                'razao_social' => 'DISTRIBUIDORA TESTE LTDA',
                'nome_fantasia' => 'DISTRIBUIDORA TESTE',
                'descricao_situacao_cadastral' => 'ATIVA',
                'email' => 'financeiro@example.com',
                'ddd_telefone_1' => '1633334444',
                'logradouro' => 'RUA CENTRAL',
                'numero' => '100',
                'bairro' => 'CENTRO',
                'municipio' => 'RIBEIRAO PRETO',
                'uf' => 'SP',
                'cep' => '14000000',
                'cnae_fiscal_descricao' => 'Comercio atacadista',
            ]),
        ]);

        $data = (new CnpjLookupService())->lookup('11.222.333/0001-44');

        $this->assertSame('11222333000144', $data['document']);
        $this->assertSame('DISTRIBUIDORA TESTE LTDA', $data['name']);
        $this->assertSame('ATIVA', $data['legal_status']);
        $this->assertSame('SP', $data['state']);
    }
}
