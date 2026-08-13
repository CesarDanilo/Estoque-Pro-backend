<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailValidationService
{
    private readonly string $apiKey;
    private readonly string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.abstract.email_validation_key');
        $this->apiUrl = config('services.abstract.email_validation_url');
    }

    public function validate(string $email): array
    {
        $response = Http::timeout(5)
            ->get($this->apiUrl, [
                'api_key' => $this->apiKey,
                'email' => $email,
            ]);

        if ($response->failed()) {
            Log::warning('Abstract Email Reputation API falhou', [
                'status' => $response->status(),
                'email' => $email,
            ]);

            throw new \RuntimeException('Não foi possível validar o email no momento.');
        }

        $data = $response->json();

        return [
            'email' => $data['email_address'] ?? $email,
            'suggested_correction' => $data['suggested_correction'] ?? null,

            'deliverability_status' => $data['email_deliverability']['status'] ?? null,
            'deliverability_detail' => $data['email_deliverability']['status_detail'] ?? null,
            'is_format_valid' => $data['email_deliverability']['is_format_valid'] ?? false,
            'is_smtp_valid' => $data['email_deliverability']['is_smtp_valid'] ?? null,
            'is_mx_valid' => $data['email_deliverability']['is_mx_valid'] ?? null,

            'quality_score' => $data['email_quality']['score'] ?? null,
            'is_free_email' => $data['email_quality']['is_free_email'] ?? null,
            'is_disposable_email' => $data['email_quality']['is_disposable'] ?? null,
            'is_role_email' => $data['email_quality']['is_role'] ?? null,
            'is_catchall' => $data['email_quality']['is_catchall'] ?? null,

            'address_risk' => $data['email_risk']['address_risk_status'] ?? null,
            'domain_risk' => $data['email_risk']['domain_risk_status'] ?? null,
        ];
    }

    public function isDeliverable(string $email): bool
    {
        $result = $this->validate($email);

        return $result['is_format_valid']
            && $result['deliverability_status'] === 'deliverable'
            && ! $result['is_disposable_email'];
    }
}