<?php

namespace App\Modules\Warehouse\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPackedRequest extends FormRequest
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
            'package_weight' => ['nullable', 'numeric', 'min:0'],
            'package_length' => ['nullable', 'numeric', 'min:0'],
            'package_width' => ['nullable', 'numeric', 'min:0'],
            'package_height' => ['nullable', 'numeric', 'min:0'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
