<?php

namespace App\Modules\Waybill\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidWaybillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
