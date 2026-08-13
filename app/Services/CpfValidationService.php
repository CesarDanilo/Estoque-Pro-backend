<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CpfValidationService
{
    private readonly string $apiKey;
    private readonly string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.cpfhub.api_key');
        $this->apiUrl = config('services.cpfhub.api_url');
    }

    public function validate(string $cpf): array
    {
        $digits = preg_replace('/\D/', '', $cpf);

        $response = Http::timeout(5)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->get("{$this->apiUrl}/{$digits}");

        if ($response->status() === 404) {
            return [
                'cpf' => $digits,
                'exists' => false,
                'name' => null,
                'birth_date' => null,
                'gender' => null,
            ];
        }

        if ($response->failed()) {
            Log::warning('CPFHub API falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
                'cpf' => $digits,
            ]);

            throw new \RuntimeException('Não foi possível validar o CPF no momento.');
        }

        $data = $response->json();

        return [
            'cpf' => $data['data']['cpf'] ?? $digits,
            'exists' => (bool) ($data['success'] ?? false),
            'name' => $data['data']['name'] ?? null,
            'birth_date' => $data['data']['birthDate'] ?? null,
            'gender' => $data['data']['gender'] ?? null,
        ];
    }
}