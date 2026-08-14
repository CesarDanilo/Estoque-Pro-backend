<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    /**
     * Lista todos os grupos do usuário logado trazendo contagem apenas de produtos.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Group::query()
            ->where('user_id', $userId)
            ->withCount(['products as produtos'])
            ->when($request->query('search'), function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%");
            });

        $groups = $query->latest()->paginate(15);

        // Calcula o total real de ativos do usuário no banco
        $totalAtivos = Group::where('user_id', $userId)->where('active', true)->count();

        // Adiciona o total_ativos ao retorno JSON
        $data = $groups->toArray();
        $data['total_ativos'] = $totalAtivos;

        return response()->json($data);
    }

    /**
     * Cria um novo grupo vinculado ao usuário logado.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')->where(function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                }),
            ],
            'description' => ['nullable', 'string'],
            'active'      => ['boolean'],
        ], [
            'name.unique' => 'Você já possui um grupo cadastrado com este nome.',
        ]);

        $group = $request->user()->groups()->create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active'      => $validated['active'] ?? true,
        ]);

        $group->produtos = 0;
        $group->subgrupos = 0;

        return response()->json([
            'message' => 'Grupo criado com sucesso.',
            'data'    => $group,
        ], 201);
    }

    /**
     * Exibe os detalhes de um grupo específico.
     */
    public function show(Request $request, Group $group): JsonResponse
    {
        if ($group->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $group->loadCount(['products as produtos']);
        $group->subgrupos = 0;

        return response()->json([
            'data' => $group,
        ]);
    }

    /**
     * Atualiza os dados de um grupo.
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        if ($group->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $userId = $request->user()->id;

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('groups', 'name')
                    ->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                    ->ignore($group->id),
            ],
            'description' => ['nullable', 'string'],
            'active'      => ['boolean'],
        ], [
            'name.unique' => 'Você já possui um grupo cadastrado com este nome.',
        ]);

        $group->update($validated);

        $group->loadCount(['products as produtos']);
        $group->subgrupos = 0;

        return response()->json([
            'message' => 'Grupo atualizado com sucesso.',
            'data'    => $group,
        ]);
    }

    /**
     * Remove um grupo movendo-o para a lixeira.
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        if ($group->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        try {
            // Executa a trait que insere na tabela 'trash' e remove da tabela 'groups'
            $group->moverParaLixeira();

            return response()->json([
                'message' => 'Grupo movido para a lixeira com sucesso.',
            ]);

        } catch (QueryException $ex) {
            // Captura violação de Chave Estrangeira do Postgres (ex: produtos associados a este grupo)
            if ($ex->getCode() === '23503' || str_contains($ex->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'message' => 'Não é possível excluir este grupo pois ele possui produtos cadastrados e vinculados a ele.'
                ], 409); // 409 Conflict
            }

            Log::error('Erro ao mover grupo para lixeira', [
                'error'    => $ex->getMessage(),
                'group_id' => $group->id,
                'user_id'  => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Erro interno ao tentar excluir o grupo.',
            ], 500);
        }
    }
}