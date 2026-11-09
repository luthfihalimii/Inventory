<?php

namespace App\Http\Requests\Wm;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
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
}
