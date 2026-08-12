<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
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
        // Garante que o usuário só possa ver seus próprios grupos
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

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
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