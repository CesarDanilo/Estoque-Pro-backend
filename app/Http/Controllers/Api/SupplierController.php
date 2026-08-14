<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Person::where('user_id', $request->user()->id)
            ->where('category', 'supplier');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('document', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);

        $suppliers = $query->orderBy('name', 'asc')->paginate($perPage);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'trade_name'         => 'nullable|string|max:255',
            'document'           => [
                'required',
                'string',
                'max:20',
                // 🟢 Valida a unicidade APENAS dentro do escopo do mesmo usuário
                Rule::unique('people', 'document')->where(function ($query) use ($userId) {
                    return $query->where('user_id', $userId);
                }),
            ],
            'state_registration' => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'contact_person'     => 'nullable|string|max:255',
            'zip_code'           => 'nullable|string|max:10',
            'street'             => 'nullable|string|max:255',
            'number'             => 'nullable|string|max:20',
            'complement'         => 'nullable|string|max:255',
            'neighborhood'       => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:2',
            'active'             => 'nullable|boolean',
            'notes'              => 'nullable|string',
        ]);

        $validated['user_id'] = $userId;
        $validated['category'] = 'supplier';
        $validated['type'] = 'company'; // Fornecedor é sempre pessoa jurídica

        $supplier = Person::create($validated);

        return response()->json([
            'message' => 'Fornecedor cadastrado com sucesso.',
            'data'    => $supplier,
        ], 201);
    }

    public function show(Request $request, Person $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id || $supplier->category !== 'supplier') {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        return response()->json($supplier);
    }

    public function update(Request $request, Person $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id || $supplier->category !== 'supplier') {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $userId = $request->user()->id;

        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'trade_name'         => 'nullable|string|max:255',
            'document'           => [
                'sometimes',
                'required',
                'string',
                'max:20',
                // 🟢 Valida unicidade por usuário ignorando o ID atual durante a edição
                Rule::unique('people', 'document')
                    ->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                    ->ignore($supplier->id),
            ],
            'state_registration' => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'contact_person'     => 'nullable|string|max:255',
            'zip_code'           => 'nullable|string|max:10',
            'street'             => 'nullable|string|max:255',
            'number'             => 'nullable|string|max:20',
            'complement'         => 'nullable|string|max:255',
            'neighborhood'       => 'nullable|string|max:255',
            'city'               => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:2',
            'active'             => 'nullable|boolean',
            'notes'              => 'nullable|string',
        ]);

        // Garante que a categoria/tipo nunca sejam alterados por aqui
        $validated['category'] = 'supplier';
        $validated['type'] = 'company';

        $supplier->update($validated);

        return response()->json([
            'message' => 'Fornecedor atualizado com sucesso.',
            'data'    => $supplier,
        ]);
    }

    public function destroy(Request $request, Person $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id || $supplier->category !== 'supplier') {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Fornecedor removido com sucesso.',
        ]);
    }
}