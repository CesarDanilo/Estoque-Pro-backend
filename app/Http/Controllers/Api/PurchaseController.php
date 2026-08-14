<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Lista todas as compras paginadas com filtros (busca, status e período)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $query = Purchase::with(['supplier', 'user', 'items', 'items.product'])
            ->where('user_id', $userId) // 🔒 escopo por usuário logado
            ->withCount('items')
            ->latest();

        // Filtro por Código ou Nome do Fornecedor
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por Status
        if ($request->filled('status') && $request->get('status') !== 'todos') {
            $query->where('status', $request->get('status'));
        }

        // Filtro por Fornecedor específico
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        // Filtro por Período de Datas
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->get('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->get('end_date'));
        }

        // Calcula o total de compras aguardando recebimento ('pending')
        $pendingCount = Purchase::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        $purchases = $query->paginate($request->get('per_page', 15));

        $response = $purchases->toArray();
        $response['pending_count'] = $pendingCount;

        return response()->json($response);
    }

    /**
     * Registra uma nova compra no sistema
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $purchase = $this->purchaseService->createPurchase($request->validated(), $userId);

            return response()->json([
                'message' => 'Compra registrada com sucesso!',
                'data'    => $purchase,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exibe os detalhes de uma compra específica
     */
    public function show(string $id): JsonResponse
    {
        $purchase = Purchase::with(['items.product', 'supplier', 'user'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($purchase);
    }

    /**
     * Atualiza uma compra
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $purchase = Purchase::where('user_id', $userId)
                ->findOrFail($id);

            if ($request->has('items')) {
                $validated = $request->validate([
                    'supplier_id'           => [
                        'nullable',
                        'uuid',
                        Rule::exists('people', 'id')
                            ->where('user_id', $userId)
                            ->where('category', 'supplier'),
                    ],
                    'discount_value'        => ['nullable', 'numeric', 'min:0'],
                    'discount_percentage'   => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'surcharge_value'       => ['nullable', 'numeric', 'min:0'],
                    'surcharge_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'notes'                 => ['nullable', 'string'],
                    'items'                 => ['required', 'array', 'min:1'],
                    'items.*.product_id'    => ['required', 'uuid', 'exists:products,id'],
                    'items.*.quantity'      => ['required', 'integer', 'min:1'],
                    'items.*.unit_cost'     => ['required', 'numeric', 'min:0'],
                ], [
                    'items.required'       => 'É necessário adicionar ao menos um item na compra.',
                    'items.*.product_id'   => 'Produto inválido ou não encontrado.',
                    'items.*.quantity.min' => 'A quantidade deve ser de no mínimo 1 item.',
                    'supplier_id.exists'   => 'Fornecedor inválido ou não encontrado.',
                ]);

                $purchase = $this->purchaseService->updatePurchase($purchase, $validated);

                return response()->json([
                    'message' => 'Compra atualizada com sucesso!',
                    'data'    => $purchase,
                ]);
            }

            $validated = $request->validate([
                'status' => ['sometimes', 'string', 'in:received,pending,cancelled'],
            ]);

            $purchase->update($validated);

            return response()->json([
                'message' => 'Compra atualizada com sucesso!',
                'data'    => $purchase->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove uma compra movendo-a para a lixeira
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $purchase = Purchase::where('user_id', auth()->id())
                ->findOrFail($id);

            // Executa estorno de estoque/regra via Service, se existir
            if (method_exists($this->purchaseService, 'deletePurchase')) {
                $this->purchaseService->deletePurchase($purchase);
            } else {
                // Move para a lixeira usando a trait
                $purchase->moverParaLixeira();
            }

            return response()->json([
                'message' => 'Compra movida para a lixeira com sucesso!',
            ], 200);

        } catch (QueryException $ex) {
            // Captura erro de chave estrangeira do PostgreSQL (ex: itens financeiros ou dependências vinculadas)
            if ($ex->getCode() === '23503' || str_contains($ex->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'message' => 'Não é possível excluir esta compra pois ela possui movimentações ou pendências financeiras vinculadas.'
                ], 409); // 409 Conflict
            }

            Log::error('Erro ao mover compra para lixeira', [
                'error'       => $ex->getMessage(),
                'purchase_id' => $id,
                'user_id'     => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Erro interno ao tentar excluir a compra.',
            ], 500);

        } catch (Exception $e) {
            Log::error('Erro ao excluir compra', [
                'error'       => $e->getMessage(),
                'purchase_id' => $id,
                'user_id'     => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Erro ao excluir compra: ' . $e->getMessage(),
            ], 422);
        }
    }
}