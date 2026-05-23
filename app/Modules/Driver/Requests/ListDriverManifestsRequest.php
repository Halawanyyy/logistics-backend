<?php

namespace App\Modules\Driver\Requests;

use App\Modules\Driver\Enums\DriverManifestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDriverManifestsRequest extends FormRequest
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
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'manifest_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(DriverManifestStatus::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
