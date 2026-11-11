<?php

namespace App\Http\Requests\Wm;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    /**
     * Accept "21" as shorthand for the 2021 intake year.
     */
    protected function prepareForValidation(): void
    {
        $generation = $this->input('generation');

        if (is_numeric($generation) && (int) $generation >= 0 && (int) $generation <= 99) {
            $year = 2000 + (int) $generation;

            // Keep two-digit years in the past: "99" means 1999, not 2099.
            $this->merge(['generation' => $year > now()->year ? $year - 100 : $year]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $member = $this->route('member');
        $memberId = $member instanceof Member ? $member->getKey() : null;

        return [
            'member_code' => ['required', 'string', 'max:255', Rule::unique('members', 'member_code')->ignore($memberId)],
            'name' => ['required', 'string', 'max:255'],
            'nrp' => ['required', 'string', 'max:30', Rule::unique('members', 'nrp')->ignore($memberId)],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'generation' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'membership_status' => ['required', Rule::in(['active', 'inactive'])],
            'borrowing_status' => ['required', Rule::in(['eligible', 'borrowing_blocked'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'generation.min' => 'Isi tahun angkatan 4 digit (contoh 2021) atau 2 digit (contoh 21).',
            'generation.max' => 'Tahun angkatan tidak boleh lebih dari 2100.',
        ];
    }
}
