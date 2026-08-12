<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::where('user_id', $request->user()->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('trade_name', 'like', "%{$search}%")
                  ->orWhere('document', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->get();

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'trade_name'         => 'nullable|string|max:255',
            'document'           => 'nullable|string|max:20',
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

        $validated['user_id'] = $request->user()->id;

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Fornecedor cadastrado com sucesso.',
            'data'    => $supplier,
        ], 201); // Ajustado de 210 para 201 Created
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'trade_name'         => 'nullable|string|max:255',
            'document'           => 'nullable|string|max:20',
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

        $supplier->update($validated);

        return response()->json([
            'message' => 'Fornecedor atualizado com sucesso.',
            'data'    => $supplier,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        if ($supplier->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Fornecedor removido com sucesso.',
        ]);
    }
}