<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_id'            => ['nullable', 'uuid', 'exists:people,id'],
            'payment_method'       => ['required', 'string', 'max:50'],
            'discount_value'       => ['nullable', 'numeric', 'min:0'],
            'discount_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'surcharge_value'      => ['nullable', 'numeric', 'min:0'],
            'surcharge_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                => ['nullable', 'string'],
            
            // Itens da venda (obrigatório pelo menos 1)
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'         => 'É necessário adicionar ao menos um item na venda.',
            'items.*.product_id'     => 'Produto inválido ou não encontrado.',
            'items.*.quantity.min'   => 'A quantidade deve ser de no mínimo 1 item.',
            'payment_method.required'=> 'Selecione a forma de pagamento.',
        ];
    }
}