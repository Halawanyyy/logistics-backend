<?php

namespace App\Modules\Driver\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateDriverManifestRequest extends FormRequest
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
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'manifest_date' => ['required', 'date_format:Y-m-d'],
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => [Rule::exists('shipments', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
