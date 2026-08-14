<?php

namespace App\Http\Controllers;

use App\Models\Trash;
use App\Models\Group;
use App\Models\Person;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrashController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $porPagina = $request->integer('per_page', 15);

        $query = Trash::query();

        // 1. Trata o filtro do Usuário (Busca registros do usuário logado ou sem dono explícito)
        if (Auth::check()) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('excluido_por', $userId)
                  ->orWhereNull('excluido_por');
            });
        }

        // 2. Filtro de Retenção de 7 dias
        $query->where('excluido_em', '>=', now()->subDays(7)->startOfDay());

        // 3. Filtro por Tipo/Tabela de Origem
        if ($tipo = $request->query('tipo')) {
            $tiposNormalizados = $this->normalizarTipo($tipo);
            if (!empty($tiposNormalizados)) {
                $query->whereIn('tabela_origem', $tiposNormalizados);
            }
        }

        // 4. Filtro de busca no JSON do Postgres (case-insensitive)
        if ($busca = trim($request->query('busca', ''))) {
            $termo = "%{$busca}%";
            $query->whereRaw("dados::text ILIKE ?", [$termo]);
        }

        $itens = $query
            ->orderByDesc('excluido_em')
            ->paginate($porPagina)
            ->through(function ($item) {
                // Garante parsing seguro do campo 'dados'
                $dadosArray = $item->dados;
                if (is_string($dadosArray)) {
                    $dadosArray = json_decode($dadosArray, true) ?? [];
                }

                return [
                    'id' => (string) $item->id,
                    'tabela_origem' => $item->tabela_origem,
                    'dados' => $dadosArray ?? [],
                    'excluido_em' => $item->excluido_em,
                    'dias_restantes' => method_exists($item, 'diasRestantes') 
                        ? $item->diasRestantes() 
                        : max(0, 7 - now()->diffInDays($item->excluido_em)),
                ];
            });

        return response()->json($itens);
    }

    public function restaurar(string $id): JsonResponse
    {
        $query = Trash::query();

        if (Auth::check()) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('excluido_por', $userId)
                  ->orWhereNull('excluido_por');
            });
        }

        $item = $query->findOrFail($id);

        if (method_exists($item, 'expirado') && $item->expirado()) {
            return response()->json(['erro' => 'Este item expirou e não pode mais ser restaurado.'], 410);
        }

        $modelClass = $this->resolverModel($item->tabela_origem);

        if (! $modelClass) {
            return response()->json(['erro' => 'Tipo de registro desconhecido na lixeira.'], 422);
        }

        try {
            DB::transaction(function () use ($item) {
                $dados = is_string($item->dados) ? json_decode($item->dados, true) : $item->dados;

                // Converte objetos/arrays internos em JSON formatado para inserção no banco
                foreach ($dados as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $dados[$key] = json_encode($value);
                    }
                }

                // Se o registro possuir soft deletes salvo nos dados, restaura definindo como null
                if (array_key_exists('deleted_at', $dados)) {
                    $dados['deleted_at'] = null;
                }

                // 🔴 EVITA ERRO DE UNIQUE VIOLATION (23505):
                // Verifica se o registro ainda existe na tabela de origem antes de tentar o insert
                $registroExiste = false;
                if (isset($dados['id'])) {
                    $registroExiste = DB::table($item->tabela_origem)
                        ->where('id', $dados['id'])
                        ->exists();
                }

                if ($registroExiste) {
                    // Se o registro ainda existir no banco, faz UPDATE para restaurar o estado original
                    DB::table($item->tabela_origem)
                        ->where('id', $dados['id'])
                        ->update($dados);
                } else {
                    // Se não existir, faz o INSERT limpo
                    DB::table($item->tabela_origem)->insert($dados);
                }

                // Apaga o registro da lixeira após a restauração com sucesso
                $item->delete();
            });

            return response()->json(['mensagem' => 'Item restaurado com sucesso.']);
        } catch (\Exception $e) {
            return response()->json([
                'erro' => 'Não foi possível restaurar o item. Verifique se os registros associados ainda existem.',
                'detalhes' => $e->getMessage()
            ], 422);
        }
    }

    public function destruirPermanente(string $id): JsonResponse
    {
        $query = Trash::query();

        if (Auth::check()) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('excluido_por', $userId)
                  ->orWhereNull('excluido_por');
            });
        }

        $item = $query->findOrFail($id);
        $item->delete();

        return response()->json(['mensagem' => 'Item excluído permanentemente.']);
    }

    /**
     * Mapeia variações do tipo enviado no parâmetro para as tabelas correspondentes
     */
    private function normalizarTipo(string $tipo): array
    {
        if ($tipo === 'todos') {
            return [];
        }

        return match ($tipo) {
            'products', 'produtos'      => ['products', 'produtos'],
            'people', 'pessoas'         => ['people', 'pessoas'],
            'suppliers', 'fornecedores' => ['suppliers', 'fornecedores'],
            'purchases', 'compras'      => ['purchases', 'compras'],
            'sales', 'vendas'           => ['sales', 'vendas'],
            'groups', 'grupos'          => ['groups', 'grupos'],
            default                     => [$tipo],
        };
    }

    /**
     * Mapeia a tabela_origem para o Model correspondente
     */
    private function resolverModel(string $tabelaOrigem): ?string
    {
        return match ($tabelaOrigem) {
            'products', 'produtos'         => Product::class,
            'people', 'pessoas'            => Person::class,
            'suppliers', 'fornecedores'    => Supplier::class,
            'purchases', 'compras'         => Purchase::class,
            'purchase_items'               => PurchaseItem::class,
            'sales', 'vendas'              => Sale::class,
            'sale_items'                   => SaleItem::class,
            'groups', 'grupos'             => Group::class,
            default                         => null,
        };
    }
}