<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantPortalSettingsRequest extends FormRequest
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
            'default_fulfillment_type' => ['sometimes', 'nullable', Rule::in(OrderFulfillmentType::values())],
            'default_payment_type' => ['sometimes', 'nullable', Rule::in(PaymentType::values())],
            'default_pickup_address' => ['sometimes', 'nullable', 'string'],
            'default_label_format' => ['sometimes', 'nullable', Rule::in(['html', 'pdf', 'zpl', 'tspl', 'json'])],
            'notification_preferences' => ['sometimes', 'nullable', 'array'],
            'notification_preferences.email_enabled' => ['sometimes', 'boolean'],
            'notification_preferences.sms_enabled' => ['sometimes', 'boolean'],
            'notification_preferences.whatsapp_enabled' => ['sometimes', 'boolean'],
            'notification_preferences.order_status_changed' => ['sometimes', 'boolean'],
            'notification_preferences.shipment_status_changed' => ['sometimes', 'boolean'],
            'notification_preferences.low_stock' => ['sometimes', 'boolean'],
            'notification_preferences.inbound_request_status_changed' => ['sometimes', 'boolean'],
            'notification_preferences.invoice_issued' => ['sometimes', 'boolean'],
            'notification_preferences.payout_status_changed' => ['sometimes', 'boolean'],
            'integration_preferences' => ['sometimes', 'nullable', 'array'],
            'merchant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'pricing_plan_id' => ['prohibited'],
            'wallet_balance' => ['prohibited'],
            'commission_settings' => ['prohibited'],
            'financial_settings' => ['prohibited'],
            'internal_notes' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
