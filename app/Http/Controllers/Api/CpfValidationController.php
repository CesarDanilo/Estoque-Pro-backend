<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateCpfRequest;
use App\Services\CpfValidationService;
use Illuminate\Http\JsonResponse;

class CpfValidationController extends Controller
{
    public function __construct(
        private readonly CpfValidationService $cpfValidationService,
    ) {}

    public function __invoke(ValidateCpfRequest $request): JsonResponse
    {
        try {
            $result = $this->cpfValidationService->validate(
                $request->validated('cpf')
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