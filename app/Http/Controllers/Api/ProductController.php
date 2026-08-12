<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request): JsonResponse
    {
        // Filtra estritamente pelos produtos do usuário logado
        $query = Product::where('user_id', $request->user()->id)
            ->with(['group', 'supplier']);

        // Busca rápida por nome ou SKU
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filtro por Grupo
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        // Filtro por Status (Ativo / Inativo)
        if ($request->has('active') && $request->input('active') !== null) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name', 'asc')->get();

        return response()->json($products);
    }

    // POST /api/products
    public function store(ProductRequest $request): JsonResponse
    {
        // Mescla os dados validados anexando o ID do usuário logado
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
        // Valida se o produto pertence ao usuário logado
        $this->authorizeProductOwnership($request, $product);

        return response()->json($product->load(['group', 'supplier']));
    }

    // PUT /api/products/{product}
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        // Valida se o produto pertence ao usuário logado
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
        // Valida se o produto pertence ao usuário logado
        $this->authorizeProductOwnership($request, $product);

        $product->delete();

        return response()->json([
            'message' => 'Produto excluído com sucesso!'
        ]);
    }

    /**
     * Garante que o recurso acessado pertence ao usuário logado.
     */
    private function authorizeProductOwnership(Request $request, Product $product): void
    {
        if ($product->user_id !== $request->user()->id) {
            abort(403, 'Ação não autorizada. Este produto pertence a outro usuário.');
        }
    }
}