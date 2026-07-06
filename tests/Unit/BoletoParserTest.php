<?php

namespace Tests\Unit;

use App\Services\BoletoParser;
use PHPUnit\Framework\TestCase;

class BoletoParserTest extends TestCase
{
    public function test_it_extracts_common_boleto_fields_from_text(): void
    {
        $text = <<<TXT
Beneficiario: DISTRIBUIDORA TESTE LTDA
CNPJ: 11.222.333/0001-44
Vencimento 15/07/2026
Valor do Documento R$ 1.234,56
Linha Digitavel 00190.00009 01234.567890 12345.678901 1 98760000123456
TXT;

        $parsed = (new BoletoParser())->parseText($text);

        $this->assertSame('11222333000144', $parsed['document']);
        $this->assertSame('2026-07-15', $parsed['due_date']);
        $this->assertSame('1234.56', $parsed['amount']);
        $this->assertSame('DISTRIBUIDORA TESTE LTDA', $parsed['beneficiary_name']);
        $this->assertNotNull($parsed['digitable_line']);
    }

    public function test_it_prefers_cedente_over_sacador_and_accepts_dot_dates(): void
    {
        $text = <<<TXT
Sacador/Avalista
CNPJ/CPF: 55253246000167
Sacado
  E F FARMACIAS LTDA
  (=) Valor do Documento
811,60
  Agencia/Codigo Cedente
  03129-1/000011181-3
  Cedente
 SC DISTRIBUICAO LTDA  CNPJ: 001.206.820.0001/05
  237-2 RECIBO DO SACADOBRADESCO
  Vencimento
    22.07.2026
TXT;

        $parsed = (new BoletoParser())->parseText($text);

        $this->assertSame('01206820000105', $parsed['document']);
        $this->assertSame('SC DISTRIBUICAO LTDA', $parsed['beneficiary_name']);
        $this->assertSame('2026-07-22', $parsed['due_date']);
        $this->assertSame('811.60', $parsed['amount']);
    }
}
