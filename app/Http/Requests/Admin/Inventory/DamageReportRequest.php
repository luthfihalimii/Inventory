<?php

namespace App\Http\Requests\Admin\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DamageReportRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'type' => ['required', Rule::in(['damage', 'loss', 'incomplete', 'maintenance'])],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
