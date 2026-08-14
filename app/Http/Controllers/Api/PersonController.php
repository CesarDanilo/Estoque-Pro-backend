<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    /**
     * Listar pessoas (com busca, filtro por categoria/tipo e paginação).
     */
    public function index(Request $request)
    {
        $query = Person::where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('document', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Categoria: client ou supplier
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Tipo de documento: individual ou company
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

        $validator = $this->validatePerson($request, $person->id, isUpdate: true);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $person->update($validator->validated());

        return response()->json([
            'message' => 'Pessoa atualizada com sucesso.',
            'person' => $person->fresh(),
        ]);
    }

    public function destroy(Request $request, Person $person)
    {
        abort_if($person->user_id !== $request->user()->id, 403, 'Acesso não autorizado.');

        try {
            // Executa a trait que cria o registro no Trash e apaga da tabela original
            $person->moverParaLixeira();

            return response()->json([
                'message' => 'Pessoa removida para a lixeira com sucesso.',
            ]);
        } catch (QueryException $ex) {
            // Captura violação de Chave Estrangeira (SQLSTATE 23503 do PostgreSQL)
            if ($ex->getCode() === '23503' || str_contains($ex->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'message' => 'Não é possível excluir esta pessoa pois ela possui compras, vendas ou registros vinculados ao seu histórico.'
                ], 409); // 409 Conflict
            }

            Log::error('Erro ao mover pessoa para lixeira', [
                'error' => $ex->getMessage(),
                'person_id' => $person->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Erro interno ao tentar excluir a pessoa.',
            ], 500);
        }
    }

    /**
     * Regras de validação compartilhadas entre store/update.
     */
    private function validatePerson(Request $request, ?string $ignoreId = null, bool $isUpdate = false)
    {
        $requiredRule = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $userId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'category' => [...$requiredRule, 'in:client,supplier'],
            'type' => [...$requiredRule, 'in:individual,company'],
            'name' => [...$requiredRule, 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                ...$requiredRule,
                'string',
                'max:20',
                Rule::unique('people', 'document')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($ignoreId),
            ],
            'state_registration' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ], [
            'document.unique' => 'Você já possui uma pessoa cadastrada com este documento.',
        ]);

        // Regra de negócio: fornecedor só pode ser pessoa jurídica
        $validator->after(function ($validator) use ($request) {
            $category = $request->input('category');
            $type = $request->input('type');

            if ($category === 'supplier' && $type && $type !== 'company') {
                $validator->errors()->add('type', 'Fornecedor deve ser cadastrado como pessoa jurídica.');
            }
        });

        return $validator;
    }
}