<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateEmailRequest;
use App\Services\EmailValidationService;
use Illuminate\Http\JsonResponse;

class EmailValidationController extends Controller
{
    public function __construct(
        private readonly EmailValidationService $emailValidationService,
    ) {}

    public function __invoke(ValidateEmailRequest $request): JsonResponse
    {
        try {
            $result = $this->emailValidationService->validate(
                $request->validated('email')
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