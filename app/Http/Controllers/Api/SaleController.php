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
            // withCount('items') adiciona o atributo "items_count" em cada
            // venda do resultado, sem carregar a relação inteira (SaleItem)
            // — é só um COUNT(*) via subquery, bem mais leve que with('items').
            // É esse campo que a coluna "Itens" da tela de Vendas (Vue) lê.
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
     * Atualiza uma venda.
     *
     * Esta rota é usada em dois cenários diferentes pelo front-end:
     *
     * 1) Edição completa da venda (modal "Editar venda" em NewSale.vue) —
     *    o payload sempre traz `items`. Nesse caso delegamos para
     *    SaleService::updateSale(), que devolve o estoque dos itens antigos,
     *    valida/baixa o estoque dos itens novos e recalcula subtotal/total
     *    corretamente. Era exatamente esse fluxo que estava quebrado: antes
     *    o controller ignorava `items` por completo e só atualizava
     *    status/payment_method/total, então nada era realmente salvo.
     *
     * 2) Alteração rápida de status (menu "Alterar situação" em
     *    SaleView.vue) — o payload traz só `{ status: '...' }`, sem itens.
     *    Nesse caso fazemos um update parcial simples, sem mexer em estoque.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $sale = Sale::findOrFail($id);

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
                    'data'    => $sale,
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
                'data'    => $sale->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
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