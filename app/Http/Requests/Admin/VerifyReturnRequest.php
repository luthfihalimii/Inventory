<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyReturnRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.loan_item_id' => ['required', 'integer'],
            'items.*.condition' => ['required', Rule::in(['good', 'damaged', 'incomplete', 'lost'])],
            'items.*.quantity_returned' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Inspection rows, normalised to scalars.
     *
     * @return array<int, array{loan_item_id: int, condition: string, quantity_returned: int, notes: string|null}>
     */
    public function items(): array
    {
        $items = $this->validated('items');
        $normalised = [];

        if (! is_array($items)) {
            return $normalised;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalised[] = [
                'loan_item_id' => (int) ($item['loan_item_id'] ?? 0),
                'condition' => (string) ($item['condition'] ?? 'good'),
                'quantity_returned' => (int) ($item['quantity_returned'] ?? 0),
                'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
            ];
        }

        return $normalised;
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) && $notes !== '' ? $notes : null;
    }
}
