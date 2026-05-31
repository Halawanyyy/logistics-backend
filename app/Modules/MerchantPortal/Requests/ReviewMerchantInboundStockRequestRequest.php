<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewMerchantInboundStockRequestRequest extends FormRequest
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
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
