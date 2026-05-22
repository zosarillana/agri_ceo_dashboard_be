<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'unit'           => ['sometimes', 'string', 'max:50'],
            'default_target' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}