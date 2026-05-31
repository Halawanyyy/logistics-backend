<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMerchantPortalNotificationsRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'max:100'],
            'unread_only' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
