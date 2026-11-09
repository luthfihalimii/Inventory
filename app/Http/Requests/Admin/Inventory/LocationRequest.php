<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $locationId = $location instanceof Location ? $location->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->ignore($locationId)],
            'code' => ['required', 'string', 'max:20', Rule::unique('locations', 'code')->ignore($locationId)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
