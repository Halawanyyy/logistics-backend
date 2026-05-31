<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Requests\StoreOrderRequest;

class StoreMerchantPortalOrderRequest extends StoreOrderRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['merchant_id'] = ['prohibited'];
        $rules['source'] = ['prohibited'];
        $rules['status'] = ['prohibited'];
        $rules['requires_review'] = ['prohibited'];
        $rules['review_reason'] = ['prohibited'];
        $rules['external_source'] = ['prohibited'];
        $rules['external_order_id'] = ['prohibited'];
        $rules['external_order_number'] = ['prohibited'];
        $rules['warehouse_fulfillment_status'] = ['prohibited'];

        return $rules;
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $originalOrderId = $this->input('original_order_id');
            $merchantId = $this->attributes->get('merchant_id');

            if ($originalOrderId === null || $merchantId === null) {
                return;
            }

            $belongsToMerchant = Order::query()
                ->whereKey($originalOrderId)
                ->where('merchant_id', $merchantId)
                ->exists();

            if (! $belongsToMerchant) {
                $validator->errors()->add('original_order_id', __('validation.custom.original_order_id.must_belong_to_current_merchant'));
            }
        });
    }
}
