<?php

namespace App\Http\Requests\Borrowing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBorrowRequestRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['scheduled', 'urgent'])],
            'pickup_at' => ['required', 'date'],
            'return_at' => ['required', 'date', 'after:pickup_at'],
            'usage_location' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000', 'required_if:category,urgent'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sop_accepted' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('category') !== 'scheduled' || $validator->errors()->has('pickup_at')) {
                    return;
                }

                $pickup = $this->date('pickup_at');

                if ($pickup && $pickup->startOfDay()->lt(now()->startOfDay()->addDays(2))) {
                    $validator->errors()->add('pickup_at', 'Pengajuan terjadwal minimal 2 hari sebelum pengambilan.');
                }
            },
        ];
    }
}
