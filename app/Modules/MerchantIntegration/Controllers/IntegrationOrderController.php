<?php

namespace App\Modules\MerchantIntegration\Controllers;

use App\Models\Merchant;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\Order\Actions\ConfirmOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationOrderController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ConfirmOrderAction $confirmOrderAction,
        private readonly CreateOrderAction $createOrderAction,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');
        /** @var MerchantIntegrationToken $token */
        $token = $request->attributes->get('merchant_integration_token');

        $this->ensurePermission($token, 'orders.create');

        $validated = $request->validate([
            'merchant_id' => ['prohibited'],
            'external_source' => ['required', 'string', 'max:100'],
            'external_order_id' => ['required', 'string', 'max:255'],
            'external_order_number' => ['nullable', 'string', 'max:255'],
            'order_type' => ['nullable', Rule::in(\App\Modules\Order\Enums\OrderType::values())],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_phone_alt' => ['nullable', 'string', 'max:50'],
            'delivery_governorate_id' => [
                'required',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_address' => ['required', 'string'],
            'delivery_notes' => ['nullable', 'string'],
            'pickup_governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pickup_address' => ['nullable', 'string'],
            'pickup_notes' => ['nullable', 'string'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', Rule::in(\App\Modules\Order\Enums\PaymentType::values())],
            'fulfillment_type' => ['nullable', Rule::in(\App\Modules\Order\Enums\OrderFulfillmentType::values())],
            'original_order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'swap_notes' => ['nullable', 'string'],
            'is_fragile' => ['nullable', 'boolean'],
            'allow_inspection' => ['nullable', 'boolean'],
            'requires_packaging' => ['nullable', 'boolean'],
            'package_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'waybill_mode' => ['nullable', Rule::in(['automatic', 'preprinted'])],
            'preprinted_waybill_number' => ['required_if:waybill_mode,preprinted', 'prohibited_unless:waybill_mode,preprinted', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $exists = Order::query()
            ->where('merchant_id', $merchant->id)
            ->where('external_source', $validated['external_source'])
            ->where('external_order_id', $validated['external_order_id'])
            ->exists();

        if ($exists) {
            throw new HttpResponseException(response()->json([
                'message' => __('orders.duplicate_external_order'),
            ], 422));
        }

        $order = $this->createOrderAction->execute(
            $validated,
            null,
            [
                'merchant_id' => $merchant->id,
                'source' => OrderSource::INTEGRATION,
            ],
            (bool) $this->systemSettingService->get('integration_order_requires_review_on_location_missing', true),
        );

        if (
            ! $order->requires_review
            && (bool) $this->systemSettingService->get('integration_order_auto_confirm', false)
        ) {
            $order = $this->confirmOrderAction->execute($order);
        }

        $this->auditLogService->log(
            AuditEventType::INTEGRATION_ORDER_CREATED,
            'created',
            $order,
            null,
            [
                'metadata' => [
                    'merchant_id' => $merchant->id,
                    'token_id' => $token->id,
                    'provider' => $token->provider,
                    'external_source' => $order->external_source,
                    'external_order_id' => $order->external_order_id,
                ],
                'notes' => 'Order created through merchant integration token.',
            ],
        );

        return (new OrderResource($order->fresh()->load([
            'merchant',
            'deliveryGovernorate',
            'deliveryArea',
            'originalOrder',
            'shipment',
            'latestStockReservation',
            'items',
            'waybill',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    private function ensurePermission(MerchantIntegrationToken $token, string $permission): void
    {
        $permissions = $token->permissions ?? [];

        if (! in_array($permission, $permissions, true)) {
            throw new HttpResponseException(response()->json([
                'message' => __('integrations.missing_permission'),
            ], 403));
        }
    }
}
