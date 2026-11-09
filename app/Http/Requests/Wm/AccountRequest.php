<?php

namespace App\Http\Requests\Wm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'role' => ['required', Rule::in(['user', 'admin_korlab', 'wm'])],
            'password' => ['required', 'string', Password::default()],
        ];
    }
}
