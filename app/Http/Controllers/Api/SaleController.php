<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Lista todas as vendas paginadas com filtros simples
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user'])
            ->latest();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('code', 'like', "%{$search}%");
        }

        $sales = $query->paginate($request->get('per_page', 15));

        return response()->json($sales);
    }

    /**
     * Registra uma nova venda no sistema
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $sale = $this->saleService->createSale($request->validated(), $userId);

            return response()->json([
                'message' => 'Venda realizada com sucesso!',
                'data'    => $sale,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de uma venda específica
     */
    public function show(string $id): JsonResponse
    {
        $sale = Sale::with(['items.product', 'customer', 'user'])->findOrFail($id);

        return response()->json($sale);
    }
}