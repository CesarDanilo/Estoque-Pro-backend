<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            // 🔴 AQUI: fornecedor agora vive em "people" (category = supplier)
            'supplier_id' => [
                'nullable',
                'uuid',
                Rule::exists('people', 'id')
                    ->where('user_id', $userId)
                    ->where('category', 'supplier'),
            ],
            'discount_value'        => ['nullable', 'numeric', 'min:0'],
            'discount_percentage'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'surcharge_value'       => ['nullable', 'numeric', 'min:0'],
            'surcharge_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                 => ['nullable', 'string'],

            // Itens da compra (obrigatório pelo menos 1)
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.unit_cost'     => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'       => 'É necessário adicionar ao menos um item na compra.',
            'items.*.product_id'   => 'Produto inválido ou não encontrado.',
            'items.*.quantity.min' => 'A quantidade deve ser de no mínimo 1 item.',
            'supplier_id.exists'   => 'Fornecedor inválido ou não encontrado.',
        ];
    }
}