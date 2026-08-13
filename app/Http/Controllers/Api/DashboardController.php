<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Retorna o resumo de métricas dos Cards superiores
     */
    public function summary(): JsonResponse
    {
        $hoje = Carbon::today();

        // 1. Vendas de Hoje
        $vendasHoje = Sale::whereDate('created_at', $hoje)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 2. Total Geral de Vendas
        $totalVendas = Sale::where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 3. Contagem de Produtos
        $produtosTotal = Product::count();
        $produtosAtivos = Product::where('active', true)->count();
        
        $estoqueBaixo = Product::where('active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->count();

        return response()->json([
            'vendas_hoje' => [
                'total' => (float) ($vendasHoje->total ?? 0),
                'count' => (int) ($vendasHoje->count ?? 0),
            ],
            'total_vendas' => [
                'total' => (float) ($totalVendas->total ?? 0),
                'count' => (int) ($totalVendas->count ?? 0),
            ],
            'produtos' => [
                'total'         => $produtosTotal,
                'ativos'        => $produtosAtivos,
                'estoque_baixo' => $estoqueBaixo,
            ]
        ]);
    }

    /**
     * Retorna os produtos mais vendidos nos últimos X dias
     */
    public function topProducts(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $limit = (int) $request->get('limit', 5);
        $startDate = Carbon::now()->subDays($days);

        // Expressão compatível com PostgreSQL para calcular total
        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $topProducts = SaleItem::query()
            ->select(
                'sale_items.product_id',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as total_amount")
            )
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'cancelled')
            ->where('sales.created_at', '>=', $startDate)
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id')
            ->orderByRaw('SUM(sale_items.quantity) DESC')
            ->limit($limit)
            ->with(['product:id,name,sku,sale_price,group_id', 'product.group:id,name'])
            ->get();

        return response()->json($topProducts);
    }

    /**
     * Retorna vendas agrupadas por Grupo/Categoria de Produto
     */
    public function salesByGroup(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        // Expressão compatível com PostgreSQL para calcular total
        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $salesByGroup = SaleItem::query()
            ->select(
                'groups.id as group_id',
                DB::raw('MAX(groups.name) as grupo'),
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as valor")
            )
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('groups', 'groups.id', '=', 'products.group_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'cancelled')
            ->where('sales.created_at', '>=', $startDate)
            ->groupBy('groups.id')
            ->orderByRaw("SUM({$itemTotalExpr}) DESC")
            ->get();

        return response()->json($salesByGroup);
    }

    /**
     * Retorna o gráfico de vendas diárias dos últimos X dias
     */
    public function dailySales(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 15);
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $vendas = Sale::select(
                DB::raw('DATE(created_at) as data'),
                DB::raw('CAST(SUM(total) AS DECIMAL(10,2)) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('data', 'ASC')
            ->get();

        return response()->json($vendas);
    }

    /**
     * Retorna a lista de produtos ativos que NÃO tiveram vendas no período
     */
    public function productsWithoutSales(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);

        $soldProductIds = SaleItem::whereHas('sale', function ($q) use ($startDate) {
            $q->where('status', '!=', 'cancelled')
              ->where('created_at', '>=', $startDate);
        })->pluck('product_id')->filter()->unique();

        $products = Product::where('active', true)
            ->whereNotIn('id', $soldProductIds)
            ->select('id', 'name', 'stock_quantity', 'sku')
            ->get();

        return response()->json($products);
    }
}