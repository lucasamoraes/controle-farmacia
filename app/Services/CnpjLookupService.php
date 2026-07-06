<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class CnpjLookupService
{
    public function lookup(?string $document): ?array
    {
        $cnpj = $this->onlyDigits($document);

        if (strlen($cnpj) !== 14) {
            return null;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

            if (! $response->successful()) {
                return null;
            }

            return $this->mapBrasilApi($response->json());
        } catch (Throwable) {
            return null;
        }
    }

    private function mapBrasilApi(array $data): array
    {
        $activity = $data['cnae_fiscal_descricao'] ?? null;

        return [
            'document' => $this->onlyDigits($data['cnpj'] ?? null),
            'name' => $data['razao_social'] ?? null,
            'trade_name' => $data['nome_fantasia'] ?? null,
            'legal_status' => $data['descricao_situacao_cadastral'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $this->phone($data),
            'street' => $data['logradouro'] ?? null,
            'number' => $data['numero'] ?? null,
            'district' => $data['bairro'] ?? null,
            'city' => $data['municipio'] ?? null,
            'state' => $data['uf'] ?? null,
            'zip_code' => $this->onlyDigits($data['cep'] ?? null),
            'main_activity' => $activity,
            'source' => 'brasilapi',
        ];
    }

    private function phone(array $data): ?string
    {
        $ddd = $this->onlyDigits($data['ddd_telefone_1'] ?? null);
        $fallback = $this->onlyDigits($data['telefone'] ?? null);

        return $ddd ?: ($fallback ?: null);
    }

    private function onlyDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
