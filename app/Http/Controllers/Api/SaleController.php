<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            ->where('user_id', auth()->id()) // 🔒 escopo por usuário logado
            ->withCount('items')
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

            // Carrega os relacionamentos para retornar o registro atualizado ao Vue
            $sale->load(['items.product', 'customer']);

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
        $sale = Sale::with(['items.product', 'customer', 'user'])
            ->where('user_id', auth()->id()) // 🔒 escopo por usuário logado
            ->findOrFail($id);

        return response()->json($sale);
    }

    /**
     * Atualiza uma venda.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $sale = Sale::where('user_id', auth()->id()) // 🔒 escopo por usuário logado
                ->findOrFail($id);

            // Cenário 1: edição completa (traz itens) — usa o SaleService
            if ($request->has('items')) {
                $validated = $request->validate([
                    'person_id'            => ['nullable', 'uuid', 'exists:people,id'],
                    'payment_method'       => ['required', 'string', 'max:50'],
                    'discount_value'       => ['nullable', 'numeric', 'min:0'],
                    'discount_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'surcharge_value'      => ['nullable', 'numeric', 'min:0'],
                    'surcharge_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'notes'                => ['nullable', 'string'],
                    'items'                => ['required', 'array', 'min:1'],
                    'items.*.product_id'   => ['required', 'uuid', 'exists:products,id'],
                    'items.*.quantity'     => ['required', 'integer', 'min:1'],
                    'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
                ], [
                    'items.required'          => 'É necessário adicionar ao menos um item na venda.',
                    'items.*.product_id'      => 'Produto inválido ou não encontrado.',
                    'items.*.quantity.min'    => 'A quantidade deve ser de no mínimo 1 item.',
                    'payment_method.required' => 'Selecione a forma de pagamento.',
                ]);

                $sale = $this->saleService->updateSale($sale, $validated);

                return response()->json([
                    'message' => 'Venda atualizada com sucesso!',
                    'data'    => $sale->load(['items.product', 'customer']),
                ]);
            }

            // Cenário 2: update parcial (ex: apenas status ou forma de pagamento)
            $validated = $request->validate([
                'status'         => ['sometimes', 'string', 'in:completed,pending,processing,cancelled,refunded'],
                'payment_method' => ['sometimes', 'string', 'max:50'],
            ]);

            $sale->update($validated);

            return response()->json([
                'message' => 'Venda atualizada com sucesso!',
                'data'    => $sale->fresh(['items.product', 'customer']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove uma venda movendo-a para a lixeira
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $sale = Sale::where('user_id', auth()->id()) // 🔒 escopo por usuário logado
                ->findOrFail($id);

            // Se você tiver estorno de estoque/regras de negócio no SaleService
            if (method_exists($this->saleService, 'deleteSale')) {
                $this->saleService->deleteSale($sale);
            } else {
                // Caso contrário, move diretamente para a lixeira
                $sale->moverParaLixeira();
            }

            return response()->json([
                'message' => 'Venda movida para a lixeira com sucesso!',
            ], 200);

        } catch (QueryException $ex) {
            // Captura restrição de Foreign Key (ex: itens financeiros ou registros filhos atrelados)
            if ($ex->getCode() === '23503' || str_contains($ex->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'message' => 'Não é possível excluir esta venda pois ela possui registros financeiros ou dependências vinculadas.'
                ], 409); // 409 Conflict
            }

            Log::error('Erro ao mover venda para lixeira', [
                'error'   => $ex->getMessage(),
                'sale_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Erro interno ao tentar excluir a venda.',
            ], 500);

        } catch (Exception $e) {
            Log::error('Erro ao excluir venda', [
                'error'   => $e->getMessage(),
                'sale_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Erro ao excluir venda: ' . $e->getMessage(),
            ], 422);
        }
    }
}