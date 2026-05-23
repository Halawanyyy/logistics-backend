<?php

namespace App\Modules\Driver\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseDriverManifestRequest extends FormRequest
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
            'actual_received_amount' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
