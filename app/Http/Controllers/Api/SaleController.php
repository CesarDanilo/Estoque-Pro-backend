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
     * Lista todas as vendas paginadas com filtros (busca, status, pagamento e período)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user'])
            ->latest();

        // Filtro por Código ou Nome do Cliente
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por Status
        if ($request->filled('status') && $request->get('status') !== 'todos') {
            $query->where('status', $request->get('status'));
        }

        // Filtro por Forma de Pagamento
        if ($request->filled('payment_method') && $request->get('payment_method') !== 'todos') {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Filtro por Período de Datas
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->get('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->get('end_date'));
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

    /**
     * Atualiza uma venda / altera o status
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $sale = Sale::findOrFail($id);

            // Atualiza apenas os campos enviados (ex: status, payment_method)
            $sale->update($request->only(['status', 'payment_method', 'total']));

            return response()->json([
                'message' => 'Venda atualizada com sucesso!',
                'data'    => $sale,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar a venda: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove uma venda do sistema
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $sale = Sale::findOrFail($id);
            $sale->delete();

            return response()->json([
                'message' => 'Venda excluída com sucesso!',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir venda: ' . $e->getMessage(),
            ], 422);
        }
    }
}