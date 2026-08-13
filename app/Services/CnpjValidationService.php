<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CnpjValidationService
{
    private readonly string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.brasilapi.cnpj_url');
    }

    public function validate(string $cnpj): array
    {
        $digits = preg_replace('/\D/', '', $cnpj);

        $response = Http::timeout(8)
            ->acceptJson()
            ->get("{$this->apiUrl}/{$digits}");

        if ($response->status() === 404) {
            return [
                'cnpj' => $digits,
                'exists' => false,
                'name' => null,
                'trade_name' => null,
                'situation' => null,
                'city' => null,
                'state' => null,
            ];
        }

        if ($response->failed()) {
            Log::warning('BrasilAPI CNPJ falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
                'cnpj' => $digits,
            ]);

            throw new \RuntimeException('Não foi possível validar o CNPJ no momento.');
        }

        $data = $response->json();

        return [
            'cnpj' => $data['cnpj'] ?? $digits,
            'exists' => true,
            'name' => $data['razao_social'] ?? null,
            'trade_name' => $data['nome_fantasia'] ?? null,
            'situation' => $data['descricao_situacao_cadastral'] ?? null,
            'city' => $data['municipio'] ?? null,
            'state' => $data['uf'] ?? null,
        ];
    }
}