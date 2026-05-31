<?php

namespace App\Modules\Label\Requests;

use App\Modules\Label\Enums\LabelFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkShipmentLabelsRequest extends FormRequest
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
            'format' => ['nullable', 'string', Rule::in(LabelFormat::values())],
            'width_mm' => ['nullable', 'numeric', 'min:20', 'max:210'],
            'height_mm' => ['nullable', 'numeric', 'min:20', 'max:297'],
            'download' => ['nullable', 'boolean'],
            'shipment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'shipment_ids.*' => [
                'required',
                'integer',
                Rule::exists('shipments', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
