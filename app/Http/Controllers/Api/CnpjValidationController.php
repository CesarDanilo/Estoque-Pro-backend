<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateCnpjRequest;
use App\Services\CnpjValidationService;
use Illuminate\Http\JsonResponse;

class CnpjValidationController extends Controller
{
    public function __construct(
        private readonly CnpjValidationService $cnpjValidationService,
    ) {}

    public function __invoke(ValidateCnpjRequest $request): JsonResponse
    {
        try {
            $result = $this->cnpjValidationService->validate(
                $request->validated('cnpj')
            );

            return response()->json([
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}