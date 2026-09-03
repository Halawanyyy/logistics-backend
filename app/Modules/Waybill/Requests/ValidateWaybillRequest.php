<?php

namespace App\Modules\Waybill\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateWaybillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['waybill_number' => ['required', 'string', 'max:255']];
    }
}
