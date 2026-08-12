<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PersonController extends Controller
{
    /**
     * Listar pessoas (com busca e paginação).
     */
    public function index(Request $request)
    {
        $query = Person::where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('document', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $people = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($people);
    }

    public function store(Request $request)
    {
        $validator = $this->validatePerson($request);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $person = Person::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Pessoa cadastrada com sucesso.',
            'person' => $person,
        ], 201);
    }

    public function show(Request $request, Person $person)
    {
        abort_if($person->user_id !== $request->user()->id, 403, 'Acesso não autorizado.');

        return response()->json($person);
    }

    public function update(Request $request, Person $person)
    {
        abort_if($person->user_id !== $request->user()->id, 403, 'Acesso não autorizado.');

        $validator = $this->validatePerson($request, $person->id);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $person->update($validator->validated());

        return response()->json([
            'message' => 'Pessoa atualizada com sucesso.',
            'person' => $person,
        ]);
    }

    public function destroy(Request $request, Person $person)
    {
        abort_if($person->user_id !== $request->user()->id, 403, 'Acesso não autorizado.');

        $person->delete();

        return response()->json([
            'message' => 'Pessoa removida com sucesso.',
        ]);
    }

    /**
     * Regras de validação compartilhadas entre store/update.
     */
    private function validatePerson(Request $request, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'type' => ['required', 'in:individual,company'],
            'name' => ['required', 'string', 'max:255'],
            'document' => [
                'required',
                'string',
                'max:20',
                'unique:people,document' . ($ignoreId ? ",{$ignoreId}" : ''),
            ],
            'gender' => ['nullable', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);
    }
}