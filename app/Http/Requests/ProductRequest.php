<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product') ? $this->route('product')->id : null;
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'sku' => [
                'required',
                'string',
                'max:30',
                // Garante que o SKU seja único apenas entre os produtos deste usuário
                Rule::unique('products', 'sku')
                    ->where('user_id', $userId)
                    ->ignore($productId)
            ],
            // Garante que o grupo informado também pertença ao usuário logado
            'group_id' => [
                'required',
                Rule::exists('groups', 'id')->where('user_id', $userId)
            ],
            // Garante que o fornecedor (se informado) pertença ao usuário logado
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('user_id', $userId)
            ],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_quantity' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
        ];
    }
}