<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    /**
     * Auxiliar para obter os dias conforme o filtro do front-end (7d, 30d, 90d)
     */
    private function getPeriodDays(Request $request): int
    {
        $period = $request->input('period', '7d');
        return match ($period) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    /**
     * Descobre dinamicamente o nome da coluna de valor total na tabela
     */
    private function getTotalColumn(string $table): string
    {
        $candidates = ['total', 'total_amount', 'total_price', 'grand_total', 'amount', 'value'];
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }
        return 'id'; // fallback de segurança
    }

    /**
     * Descobre dinamicamente a coluna de data (sale_date/purchase_date/date/created_at)
     */
    private function getDateColumn(string $table, string $preferred): string
    {
        if (Schema::hasColumn($table, $preferred)) {
            return $preferred;
        }
        if (Schema::hasColumn($table, 'date')) {
            return 'date';
        }
        return 'created_at';
    }

    // ==========================================
    // 1. RELATÓRIO DE VENDAS
    // ==========================================
    public function sales(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $days = $this->getPeriodDays($request);
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $totalCol = $this->getTotalColumn('sales');
        $dateCol = $this->getDateColumn('sales', 'sale_date');

        $salesQuery = Sale::where('user_id', $userId)
            ->where($dateCol, '>=', $startDate);

        // --- Cards Superiores ---
        $faturamento = (clone $salesQuery)
            ->where('status', 'completed')
            ->sum($totalCol) ?? 0;

        $vendasRealizadas = (clone $salesQuery)->where('status', 'completed')->count();

        $itensVendidos = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.user_id', $userId)
            ->where('sales.status', 'completed')
            ->where("sales.{$dateCol}", '>=', $startDate)
            ->sum('sale_items.quantity') ?? 0;

        $produtosComVenda = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.user_id', $userId)
            ->where("sales.{$dateCol}", '>=', $startDate)
            ->pluck('sale_items.product_id');

        $produtosSemVenda = Product::where('user_id', $userId)
            ->where('active', true)
            ->whereNotIn('id', $produtosComVenda)
            ->count();

        // --- Gráfico: Vendas por dia ---
        $chartData = Sale::where('user_id', $userId)
            ->where('status', 'completed')
            ->where($dateCol, '>=', $startDate)
            ->select(
                DB::raw("DATE({$dateCol}) as data"),
                DB::raw("SUM({$totalCol}) as valor")
            )
            ->groupBy(DB::raw("DATE({$dateCol})"))
            ->orderBy(DB::raw("DATE({$dateCol})"), 'asc')
            ->get();

        $mediaDiaria = $days > 0 ? ($faturamento / $days) : 0;
        $melhorDia = $chartData->sortByDesc('valor')->first();

        // --- Gráfico: Vendas por grupo ---
        $itemTotalCol = Schema::hasColumn('sale_items', 'total_price') ? 'sale_items.total_price' : '(sale_items.unit_price * sale_items.quantity)';

        $vendasPorGrupo = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->where('sales.user_id', $userId)
            ->where('sales.status', 'completed')
            ->where("sales.{$dateCol}", '>=', $startDate)
            ->select(
                DB::raw("COALESCE(groups.name, 'Outros') as grupo"),
                DB::raw("SUM({$itemTotalCol}) as total")
            )
            ->groupBy(DB::raw("COALESCE(groups.name, 'Outros')"))
            ->orderByDesc('total')
            ->get();

        // --- Ranking: Produtos mais vendidos (Sem limite) ---
        $produtosMaisVendidos = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.user_id', $userId)
            ->where('sales.status', 'completed')
            ->where("sales.{$dateCol}", '>=', $startDate)
            ->select(
                'products.id',
                'products.name',
                DB::raw("SUM(sale_items.quantity) as quantidade")
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantidade')
            ->get();

        return response()->json([
            'cards' => [
                'faturamento' => (float) $faturamento,
                'vendas_realizadas' => (int) $vendasRealizadas,
                'itens_vendidos' => (int) $itensVendidos,
                'produtos_sem_vendas' => (int) $produtosSemVenda,
            ],
            'vendas_por_dia' => [
                'total_periodo' => (float) $faturamento,
                'media_diaria' => (float) round($mediaDiaria, 2),
                'melhor_dia' => $melhorDia ? [
                    'data' => Carbon::parse($melhorDia->data)->format('d/m'),
                    'valor' => (float) $melhorDia->valor,
                ] : null,
                'chart' => $chartData,
            ],
            'vendas_por_grupo' => $vendasPorGrupo,
            'produtos_mais_vendidos' => $produtosMaisVendidos,
        ]);
    }

    // ==========================================
    // 2. RELATÓRIO DE COMPRAS
    // ==========================================
    public function purchases(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $days = $this->getPeriodDays($request);
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $totalCol = $this->getTotalColumn('purchases');
        $dateCol = $this->getDateColumn('purchases', 'purchase_date');

        $purchasesQuery = Purchase::where('user_id', $userId)
            ->where($dateCol, '>=', $startDate);

        $totalComprado = (clone $purchasesQuery)->sum($totalCol) ?? 0;
        $comprasNoPeriodo = (clone $purchasesQuery)->count();

        // Produtos em Nível Crítico / Reposição
        $produtosARepor = Product::where('user_id', $userId)
            ->where('active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->count();

        // Compras por Fornecedor
        $supplierJoinCol = Schema::hasColumn('purchases', 'supplier_id') ? 'purchases.supplier_id' : null;
        $personJoinCol = Schema::hasColumn('purchases', 'person_id') ? 'purchases.person_id' : null;

        $comprasPorFornecedorQuery = DB::table('purchases')
            ->where('purchases.user_id', $userId)
            ->where("purchases.{$dateCol}", '>=', $startDate);

        if ($supplierJoinCol) {
            $comprasPorFornecedorQuery->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id');
        }
        if ($personJoinCol) {
            $comprasPorFornecedorQuery->leftJoin('people', 'people.id', '=', 'purchases.person_id');
        }

        $fornecedorNameExpr = "COALESCE(" .
            ($supplierJoinCol ? "suppliers.name, " : "") .
            ($personJoinCol ? "people.name, " : "") .
            "'Não informado')";

        $comprasPorFornecedor = $comprasPorFornecedorQuery
            ->select(
                DB::raw("{$fornecedorNameExpr} as fornecedor"),
                DB::raw("SUM(purchases.{$totalCol}) as total")
            )
            ->groupBy(DB::raw($fornecedorNameExpr))
            ->orderByDesc('total')
            ->get();

        // Produtos Vendidos que Precisam de Reposição
        $produtosReposicaoLista = Product::where('user_id', $userId)
            ->where('active', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->select('id', 'name', 'stock_quantity', 'min_stock_quantity')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock_quantity' => $p->stock_quantity,
                    'min_stock_quantity' => $p->min_stock_quantity,
                    'status_label' => $p->stock_quantity <= 0 ? 'Sem estoque' : "{$p->stock_quantity} un.",
                ];
            });

        return response()->json([
            'cards' => [
                'total_comprado' => (float) $totalComprado,
                'compras_no_periodo' => (int) $comprasNoPeriodo,
                'produtos_a_repor' => (int) $produtosARepor,
            ],
            'compras_por_fornecedor' => $comprasPorFornecedor,
            'produtos_reposicao' => $produtosReposicaoLista,
        ]);
    }

    // ==========================================
    // 3. RELATÓRIO DE PRODUTOS
    // ==========================================
    public function products(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalProdutos = Product::where('user_id', $userId)->count();

        // Distribuição de Produtos por Grupo
        $produtosPorGrupo = DB::table('products')
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->where('products.user_id', $userId)
            ->select(
                DB::raw("COALESCE(groups.name, 'Sem Grupo') as grupo"),
                DB::raw("COUNT(products.id) as quantidade")
            )
            ->groupBy(DB::raw("COALESCE(groups.name, 'Sem Grupo')"))
            ->get()
            ->map(function ($item) use ($totalProdutos) {
                $percentual = $totalProdutos > 0 ? round(($item->quantidade / $totalProdutos) * 100) : 0;
                return [
                    'grupo' => $item->grupo,
                    'quantidade' => (int) $item->quantidade,
                    'percentual' => $percentual,
                ];
            });

        // Situação do Estoque
        $estoqueNormal = Product::where('user_id', $userId)
            ->where('stock_quantity', '>', DB::raw('min_stock_quantity'))
            ->count();

        $estoqueBaixo = Product::where('user_id', $userId)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity')
            ->count();

        $semEstoque = Product::where('user_id', $userId)
            ->where('stock_quantity', '<=', 0)
            ->count();

        // Ativos x Inativos
        $ativos = Product::where('user_id', $userId)->where('active', true)->count();
        $inativos = Product::where('user_id', $userId)->where('active', false)->count();

        return response()->json([
            'produtos_por_grupo' => $produtosPorGrupo,
            'situacao_estoque' => [
                'normal' => (int) $estoqueNormal,
                'baixo' => (int) $estoqueBaixo,
                'sem_estoque' => (int) $semEstoque,
            ],
            'produtos_ativos_inativos' => [
                'ativos' => [
                    'quantidade' => (int) $ativos,
                    'percentual' => $totalProdutos > 0 ? round(($ativos / $totalProdutos) * 100) : 0,
                ],
                'inativos' => [
                    'quantidade' => (int) $inativos,
                    'percentual' => $totalProdutos > 0 ? round(($inativos / $totalProdutos) * 100) : 0,
                ]
            ]
        ]);
    }

    // ==========================================
    // 4. RELATÓRIO DE PESSOAS
    // ==========================================
    public function people(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Pessoas por Tipo/Grupo
        $clientesQtd = Person::where('user_id', $userId)->where('type', 'client')->count();
        $fornecedoresQtd = Person::where('user_id', $userId)->where('type', 'supplier')->count();
        $colaboradoresQtd = Person::where('user_id', $userId)->where('type', 'employee')->count();

        if ($fornecedoresQtd === 0) {
            $fornecedoresQtd = Supplier::where('user_id', $userId)->count();
        }

        $pessoasPorGrupo = [
            ['grupo' => 'Cliente', 'quantidade' => $clientesQtd],
            ['grupo' => 'Fornecedor', 'quantidade' => $fornecedoresQtd],
            ['grupo' => 'Colaborador', 'quantidade' => $colaboradoresQtd],
        ];

        // Pessoas por Gênero
        $generoFeminino = Person::where('user_id', $userId)->where('gender', 'F')->count();
        $generoMasculino = Person::where('user_id', $userId)->where('gender', 'M')->count();
        $generoNaoInformado = Person::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('gender')->orWhereNotIn('gender', ['M', 'F']);
            })->count();

        // Faixa Etária via PostgreSQL
        $faixasEtarias = DB::table('people')
            ->where('user_id', $userId)
            ->select(
                DB::raw("
                    COUNT(CASE WHEN birth_date IS NOT NULL AND EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 18 AND 25 THEN 1 END) as f_18_25,
                    COUNT(CASE WHEN birth_date IS NOT NULL AND EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 26 AND 35 THEN 1 END) as f_26_35,
                    COUNT(CASE WHEN birth_date IS NOT NULL AND EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 36 AND 45 THEN 1 END) as f_36_45,
                    COUNT(CASE WHEN birth_date IS NOT NULL AND EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 46 AND 60 THEN 1 END) as f_46_60,
                    COUNT(CASE WHEN birth_date IS NOT NULL AND EXTRACT(YEAR FROM AGE(birth_date)) > 60 THEN 1 END) as f_60_plus,
                    COUNT(CASE WHEN birth_date IS NULL THEN 1 END) as nao_informado
                ")
            )
            ->first();

        return response()->json([
            'pessoas_por_grupo' => $pessoasPorGrupo,
            'pessoas_por_genero' => [
                'feminino' => (int) $generoFeminino,
                'masculino' => (int) $generoMasculino,
                'nao_informado' => (int) $generoNaoInformado,
            ],
            'pessoas_por_faixa_etaria' => [
                '18_25' => (int) ($faixasEtarias->f_18_25 ?? 0),
                '26_35' => (int) ($faixasEtarias->f_26_35 ?? 0),
                '36_45' => (int) ($faixasEtarias->f_36_45 ?? 0),
                '46_60' => (int) ($faixasEtarias->f_46_60 ?? 0),
                '60_plus' => (int) ($faixasEtarias->f_60_plus ?? 0),
                'nao_informado' => (int) ($faixasEtarias->nao_informado ?? 0),
            ]
        ]);
    }
}