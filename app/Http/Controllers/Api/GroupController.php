<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // <-- IMPORTANTE: Importar a classe Rule

class GroupController extends Controller
{
    /**
     * Lista todos os grupos do usuário logado.
     */
    public function index(Request $request): JsonResponse
    {
        $groups = Group::query()
            ->where('user_id', $request->user()->id)
            ->when($request->query('search'), function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return response()->json($groups);
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
                // Garante que o nome seja único apenas entre os registros do próprio usuário
                Rule::unique('groups', 'name')->where(function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                }),
            ],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ], [
            // Opcional: Mensagem personalizada de erro
            'name.unique' => 'Você já possui um grupo cadastrado com este nome.',
        ]);

        $group = $request->user()->groups()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Grupo criado com sucesso.',
            'data' => $group,
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
                // Ignora o ID do próprio grupo na verificação de nome único
                Rule::unique('groups', 'name')
                    ->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                    ->ignore($group->id),
            ],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ], [
            'name.unique' => 'Você já possui um grupo cadastrado com este nome.',
        ]);

        $group->update($validated);

        return response()->json([
            'message' => 'Grupo atualizado com sucesso.',
            'data' => $group,
        ]);
    }

    /**
     * Remove um grupo.
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        if ($group->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $group->delete();

        return response()->json([
            'message' => 'Grupo removido com sucesso.',
        ]);
    }
}