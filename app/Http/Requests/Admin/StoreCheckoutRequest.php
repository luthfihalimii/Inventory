<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.reservation_id' => ['required', 'integer', 'distinct'],
            'lines.*.asset_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'lines.*.condition_out' => ['required', Rule::in(['good', 'damaged'])],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Handover lines, normalised to scalars.
     *
     * @return array<int, array{reservation_id: int, asset_id: int|null, quantity: int, condition_out: string, notes: string|null}>
     */
    public function lines(): array
    {
        $lines = $this->validated('lines');
        $normalised = [];

        if (! is_array($lines)) {
            return $normalised;
        }

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalised[] = [
                'reservation_id' => (int) ($line['reservation_id'] ?? 0),
                'asset_id' => isset($line['asset_id']) ? (int) $line['asset_id'] : null,
                'quantity' => (int) ($line['quantity'] ?? 0),
                'condition_out' => (string) ($line['condition_out'] ?? 'good'),
                'notes' => isset($line['notes']) ? (string) $line['notes'] : null,
            ];
        }

        return $normalised;
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? $reason : null;
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) && $notes !== '' ? $notes : null;
    }

    /**
     * @return array<string, bool>|null
     */
    public function checklist(): ?array
    {
        $checklist = $this->validated('checklist');

        if (! is_array($checklist)) {
            return null;
        }

        $normalised = [];

        foreach ($checklist as $key => $value) {
            $normalised[(string) $key] = (bool) $value;
        }

        return $normalised;
    }
}
