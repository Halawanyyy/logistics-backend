<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOrderWarehousePickingRequest extends FormRequest
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
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
        ];
    }
}
