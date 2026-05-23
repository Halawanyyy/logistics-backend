<?php

namespace App\Modules\UserManagement\Requests;

use App\Modules\UserManagement\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::values())],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ];
    }
}
