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
     * Timezone de referência para "hoje", "ontem", etc.
     * Ajuste se seu app não for pt-BR / America/Sao_Paulo.
     */
    private const TZ = 'America/Sao_Paulo';

    /**
     * Aplica o filtro "não cancelada" tratando status NULL como válido.
     * != 'cancelled' em SQL descarta linhas com status NULL — isso corrige isso.
     */
    private function scopeNaoCancelada($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')
              ->orWhere('status', '!=', 'cancelled');
        });
    }

    /**
     * Retorna o resumo de métricas dos Cards superiores
     */
    public function summary(): JsonResponse
    {
        // Início e fim do dia no timezone local, convertidos para UTC
        // (assumindo que created_at é salvo em UTC, padrão do Laravel/Postgres)
        $inicioHoje = Carbon::now(self::TZ)->startOfDay()->utc();
        $fimHoje    = Carbon::now(self::TZ)->endOfDay()->utc();

        // 1. Vendas de Hoje
        $vendasHojeQuery = Sale::whereBetween('created_at', [$inicioHoje, $fimHoje]);
        $vendasHoje = $this->scopeNaoCancelada($vendasHojeQuery)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 2. Total Geral de Vendas
        $totalVendas = $this->scopeNaoCancelada(Sale::query())
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
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $query = SaleItem::query()
            ->select(
                'sale_items.product_id',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as total_amount")
            )
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', $startDate)
            ->whereNotNull('sale_items.product_id');

        $topProducts = $this->scopeNaoCancelada($query)
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
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $query = SaleItem::query()
            ->select(
                'groups.id as group_id',
                DB::raw('MAX(groups.name) as grupo'),
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as valor")
            )
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('groups', 'groups.id', '=', 'products.group_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', $startDate);

        $salesByGroup = $this->scopeNaoCancelada($query)
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
        $startDate = Carbon::now(self::TZ)->subDays($days)->startOfDay()->utc();

        // Agrupa convertendo created_at (UTC) para o timezone local antes de extrair a data,
        // senão vendas feitas à noite "vazam" para o dia seguinte no agrupamento.
        $dataLocalExpr = "DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE '" . self::TZ . "')";

        $query = Sale::select(
                DB::raw("{$dataLocalExpr} as data"),
                DB::raw('CAST(SUM(total) AS DECIMAL(10,2)) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate);

        $vendas = $this->scopeNaoCancelada($query)
            ->groupBy(DB::raw($dataLocalExpr))
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
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $soldProductIds = SaleItem::whereHas('sale', function ($q) use ($startDate) {
            $this->scopeNaoCancelada($q)->where('created_at', '>=', $startDate);
        })->pluck('product_id')->filter()->unique();

        $products = Product::where('active', true)
            ->whereNotIn('id', $soldProductIds)
            ->select('id', 'name', 'stock_quantity', 'sku')
            ->get();

        return response()->json($products);
    }

    /**
     * Retorna os produtos com risco de desabastecimento
     * (estoque atual <= estoque mínimo), ordenados do MENOR
     * para o MAIOR estoque restante — quem está mais crítico
     * (inclusive "Sem estoque" = 0) aparece primeiro.
     *
     * Usado pelo card "Precisa da sua atenção" no dashboard.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 50);

        $produtos = Product::where('active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->with('group:id,name')
            ->orderBy('stock_quantity', 'asc') // 🔴 ordem crescente (menor estoque primeiro)
            ->limit($limit)
            ->get([
                'id',
                'name',
                'sku',
                'group_id',
                'stock_quantity',
                'min_stock_quantity',
            ]);

        return response()->json($produtos);
    }
}