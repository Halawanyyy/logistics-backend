<?php

namespace App\Modules\UserManagement\Requests;

use App\Modules\UserManagement\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');
        $userId = $user?->id ?? $user;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::values())],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ];
    }
}
