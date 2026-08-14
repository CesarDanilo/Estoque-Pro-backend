<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('user_id', $request->user()->id)
            ->with(['group', 'supplier']);

        // Busca por nome
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Filtro por Grupo
        if ($request->filled('group_id') && $request->input('group_id') !== 'todos') {
            $query->where('group_id', $request->input('group_id'));
        }

        // Filtro por Status (Ativo / Inativo)
        if ($request->has('active') && $request->input('active') !== null && $request->input('active') !== 'todos') {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name', 'asc')->get();

        return response()->json($products);
    }

    // POST /api/products
    public function store(ProductRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]);

        $product = Product::create($data);

        return response()->json([
            'message' => 'Produto cadastrado com sucesso!',
            'data' => $product->load(['group', 'supplier'])
        ], 201);
    }

    // GET /api/products/{product}
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProductOwnership($request, $product);

        return response()->json($product->load(['group', 'supplier']));
    }

    // PUT /api/products/{product}
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->authorizeProductOwnership($request, $product);

        $product->update($request->validated());

        return response()->json([
            'message' => 'Produto atualizado com sucesso!',
            'data' => $product->load(['group', 'supplier'])
        ]);
    }

    // DELETE /api/products/{product}
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProductOwnership($request, $product);

        try {
            // Executa a trait que insere na tabela 'trash' e remove da tabela 'products'
            $product->moverParaLixeira();

            return response()->json([
                'message' => 'Produto movido para a lixeira com sucesso!'
            ]);

        } catch (QueryException $ex) {
            // Captura erro de Foreign Key do Postgres (ex: produto em vendas ou itens de compra)
            if ($ex->getCode() === '23503' || str_contains($ex->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'message' => 'Não é possível excluir este produto pois ele possui vendas, compras ou movimentações vinculadas ao seu histórico.'
                ], 409); // 409 Conflict
            }

            Log::error('Erro ao mover produto para lixeira', [
                'error' => $ex->getMessage(),
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Erro interno ao tentar excluir o produto.'
            ], 500);
        }
    }

    private function authorizeProductOwnership(Request $request, Product $product): void
    {
        if ($product->user_id !== $request->user()->id) {
            abort(403, 'Ação não autorizada. Este produto pertence a outro usuário.');
        }
    }
}