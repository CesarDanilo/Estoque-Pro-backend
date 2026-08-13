<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
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
     */
    private const TZ = 'America/Sao_Paulo';

    /**
     * Aplica o filtro "não cancelada" tratando status NULL como válido.
     */
    private function scopeNaoCancelada($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')
              ->orWhere('status', '!=', 'cancelled');
        });
    }

    /**
     * Retorna o ID do usuário autenticado.
     */
    private function userId(): string|int
    {
        return auth()->id();
    }

    /**
     * Retorna o resumo de métricas dos Cards superiores (Todas isoladas por usuário)
     */
    public function summary(): JsonResponse
    {
        $userId = $this->userId();

        $inicioHoje = Carbon::now(self::TZ)->startOfDay()->utc();
        $fimHoje    = Carbon::now(self::TZ)->endOfDay()->utc();

        // 1. Vendas de Hoje
        $vendasHojeQuery = Sale::where('user_id', $userId)
            ->whereBetween('created_at', [$inicioHoje, $fimHoje]);
        $vendasHoje = $this->scopeNaoCancelada($vendasHojeQuery)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 2. Total Geral de Vendas
        $totalVendas = $this->scopeNaoCancelada(
                Sale::where('user_id', $userId)
            )
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 3. Compras de Hoje
        $comprasHojeQuery = Purchase::where('user_id', $userId)
            ->whereBetween('created_at', [$inicioHoje, $fimHoje]);
        $comprasHoje = $this->scopeNaoCancelada($comprasHojeQuery)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 4. Total Geral de Compras
        $totalCompras = $this->scopeNaoCancelada(
                Purchase::where('user_id', $userId)
            )
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // 5. Contagem de Produtos (Isolado por usuário)
        $produtosTotal = Product::where('user_id', $userId)->count();
        $produtosAtivos = Product::where('user_id', $userId)
            ->where('active', true)
            ->count();

        $estoqueBaixo = Product::where('user_id', $userId)
            ->where('active', true)
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
            'compras_hoje' => [
                'total' => (float) ($comprasHoje->total ?? 0),
                'count' => (int) ($comprasHoje->count ?? 0),
            ],
            'total_compras' => [
                'total' => (float) ($totalCompras->total ?? 0),
                'count' => (int) ($totalCompras->count ?? 0),
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
        $userId = $this->userId();
        $days = (int) $request->get('days', 30);
        $limit = (int) $request->get('limit', 5);
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $query = SaleItem::query()
            ->select(
                'sale_items.product_id',
                DB::raw('COALESCE(SUM(sale_items.quantity), 0) as total_quantity'),
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as total_amount")
            )
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.user_id', $userId) // 🔒 Escopo por usuário
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
        $userId = $this->userId();
        $days = (int) $request->get('days', 30);
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $itemTotalExpr = 'COALESCE(sale_items.quantity * sale_items.unit_price, 0)';

        $query = SaleItem::query()
            ->select(
                'groups.id as group_id',
                'groups.name as grupo',
                DB::raw("CAST(SUM({$itemTotalExpr}) AS DECIMAL(10,2)) as valor")
            )
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->where('sales.user_id', $userId) // 🔒 Escopo por usuário
            ->where('sales.created_at', '>=', $startDate);

        $salesByGroup = $this->scopeNaoCancelada($query)
            ->groupBy('groups.id', 'groups.name')
            ->orderByRaw("SUM({$itemTotalExpr}) DESC")
            ->get()
            ->map(function ($item) {
                return [
                    'group_id' => $item->group_id ?? 0,
                    'grupo'    => $item->grupo ?? 'Sem Categoria',
                    'valor'    => (float) $item->valor,
                ];
            });

        return response()->json($salesByGroup);
    }
    
    /**
     * Retorna o gráfico de vendas diárias dos últimos X dias.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $userId = $this->userId();
        $days = (int) $request->get('days', 15);
        $startDate = Carbon::now(self::TZ)->subDays($days)->startOfDay()->utc();

        $dataLocalExpr = "DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE '" . self::TZ . "')";

        $query = Sale::select(
                DB::raw("{$dataLocalExpr} as data"),
                DB::raw('CAST(SUM(total) AS DECIMAL(10,2)) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('user_id', $userId) // 🔒 Escopo por usuário
            ->where('created_at', '>=', $startDate);

        $vendas = $this->scopeNaoCancelada($query)
            ->groupBy(DB::raw($dataLocalExpr))
            ->orderBy('data', 'ASC')
            ->get();

        return response()->json($vendas);
    }

    /**
     * Retorna a lista de produtos ativos que NÃO tiveram vendas no período.
     */
    public function productsWithoutSales(Request $request): JsonResponse
    {
        $userId = $this->userId();
        $days = (int) $request->get('days', 30);
        $startDate = Carbon::now(self::TZ)->subDays($days)->utc();

        $soldProductIds = SaleItem::whereHas('sale', function ($q) use ($startDate, $userId) {
            $this->scopeNaoCancelada($q)
                ->where('user_id', $userId) // 🔒 Escopo por usuário na subquery
                ->where('created_at', '>=', $startDate);
        })->pluck('product_id')->filter()->unique();

        $products = Product::where('user_id', $userId) // 🔒 Escopo por usuário
            ->where('active', true)
            ->whereNotIn('id', $soldProductIds)
            ->select('id', 'name', 'stock_quantity', 'sku')
            ->get();

        return response()->json($products);
    }

    /**
     * Retorna os produtos com risco de desabastecimento.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $userId = $this->userId();
        $limit = (int) $request->get('limit', 50);

        $produtos = Product::where('user_id', $userId) // 🔒 Escopo por usuário
            ->where('active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->with('group:id,name')
            ->orderBy('stock_quantity', 'asc')
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

    /**
     * Retorna as últimas movimentações recentes (Vendas + Compras) mescladas por data.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $userId = $this->userId();
        $limit = (int) $request->get('limit', 10);

        // Busca Vendas recentes do usuário
        $vendasQuery = Sale::where('user_id', $userId)->with('customer:id,name');
        $vendas = $this->scopeNaoCancelada($vendasQuery)
            ->latest()
            ->limit($limit)
            ->get();

        // Busca Compras recentes do usuário
        $comprasQuery = Purchase::where('user_id', $userId)->with('supplier:id,name');
        $compras = $this->scopeNaoCancelada($comprasQuery)
            ->latest()
            ->limit($limit)
            ->get();

        // Formata as Vendas (Saídas)
        $atividadesVendas = $vendas->map(function ($v) {
            return [
                'id' => $v->id,
                'tipo' => 'saida',
                'titulo' => 'Venda ' . ($v->code ?? substr((string)$v->id, 0, 8)) . ' · ' . ($v->customer->name ?? 'Cliente Avulso'),
                'data' => $v->created_at,
                'valor' => (float) ($v->total ?? 0),
                'raw' => $v,
            ];
        });

        // Formata as Compras (Entradas)
        $atividadesCompras = $compras->map(function ($c) {
            return [
                'id' => $c->id,
                'tipo' => 'entrada',
                'titulo' => 'Compra ' . ($c->code ?? substr((string)$c->id, 0, 8)) . ' · ' . ($c->supplier->name ?? 'Fornecedor Avulso'),
                'data' => $c->created_at,
                'valor' => (float) ($c->total ?? 0),
                'raw' => $c,
            ];
        });

        // Junta, ordena pela data mais recente e limita
        $atividades = $atividadesVendas->concat($atividadesCompras)
            ->sortByDesc('data')
            ->values()
            ->take($limit);

        return response()->json($atividades);
    }
}