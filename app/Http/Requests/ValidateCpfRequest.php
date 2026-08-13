<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCpfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $digits = preg_replace('/\D/', '', $this->input('cpf', ''));

            if (strlen($digits) !== 11) {
                $validator->errors()->add('cpf', 'O CPF deve conter 11 dígitos.');
            }
        });
    }
}