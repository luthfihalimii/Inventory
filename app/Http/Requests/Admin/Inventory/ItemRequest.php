<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $itemId = $item instanceof Item ? $item->getKey() : null;

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('items', 'code')->ignore($itemId)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'tracking_method' => ['required', Rule::in(['serialized', 'quantity'])],
            'quantity_total' => ['nullable', 'integer', 'min:0', 'required_if:tracking_method,quantity', 'prohibited_if:tracking_method,serialized'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'acquired_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
