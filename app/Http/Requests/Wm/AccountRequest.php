<?php

namespace App\Http\Requests\Wm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'role' => ['required', Rule::in(['user', 'admin_korlab', 'wm'])],
            'password' => ['required', 'string', Password::default()],
        ];
    }
}
