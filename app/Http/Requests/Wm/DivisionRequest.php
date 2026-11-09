<?php

namespace App\Http\Requests\Wm;

use App\Models\Division;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivisionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $division = $this->route('division');
        $divisionId = $division instanceof Division ? $division->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('divisions', 'name')->ignore($divisionId)],
            'code' => ['required', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($divisionId)],
        ];
    }
}
