<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Driver\Enums\DriverStatus;
use App\Modules\Driver\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDriverRequest extends FormRequest
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
            'driver_id' => [
                'required',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $driver = Driver::query()->find($this->integer('driver_id'));

            if ($driver !== null && $driver->status !== DriverStatus::ACTIVE) {
                $validator->errors()->add('driver_id', __('validation.custom.driver_id.inactive'));
            }
        });
    }
}
