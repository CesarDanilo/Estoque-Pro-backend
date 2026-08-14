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
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            // 🔴 AQUI: removida validação de 'sku' — campo não é mais usado
            'group_id' => [
                'required',
                Rule::exists('groups', 'id')->where('user_id', $userId)
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('people', 'id')
                    ->where('user_id', $userId)
                    ->where('category', 'supplier'),
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