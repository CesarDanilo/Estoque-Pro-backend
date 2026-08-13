<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCnpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $digits = preg_replace('/\D/', '', $this->input('cnpj', ''));

            if (strlen($digits) !== 14) {
                $validator->errors()->add('cnpj', 'O CNPJ deve conter 14 dígitos.');
            }
        });
    }
}