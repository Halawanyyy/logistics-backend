<?php

namespace App\Modules\Label\Requests;

use App\Modules\Label\Enums\LabelFormat;
use App\Modules\Label\Enums\LabelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelPreviewRequest extends FormRequest
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
            'label_type' => ['nullable', 'string', Rule::in(LabelType::values())],
            'format' => ['nullable', 'string', Rule::in(LabelFormat::values())],
            'width_mm' => ['nullable', 'numeric', 'min:20', 'max:210'],
            'height_mm' => ['nullable', 'numeric', 'min:20', 'max:297'],
            'download' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'array', 'max:20'],
            'lines.*.label' => ['nullable', 'string', 'max:255'],
            'lines.*.value' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
