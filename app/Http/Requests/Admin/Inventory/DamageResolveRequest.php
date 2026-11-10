<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DamageResolveRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['repaired', 'written_off'])],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'vendor' => ['nullable', 'string', 'max:200'],
        ];
    }
}
