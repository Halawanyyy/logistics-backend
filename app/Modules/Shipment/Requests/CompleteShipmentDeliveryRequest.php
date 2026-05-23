<?php

namespace App\Modules\Shipment\Requests;

use App\Modules\Order\Models\OrderItem;
use App\Modules\Shipment\Enums\ReturnCondition;
use App\Modules\Shipment\Enums\ShipmentItemResultStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\ShipmentItemResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteShipmentDeliveryRequest extends FormRequest
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
            'shipment_status' => ['required', Rule::in([
                ShipmentStatus::DELIVERED,
                ShipmentStatus::PARTIALLY_DELIVERED,
                ShipmentStatus::FAILED,
            ])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', Rule::exists('order_items', 'id')],
            'items.*.warehouse_product_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.result_status' => ['required', Rule::in(ShipmentItemResultStatus::values())],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.return_condition' => ['nullable', Rule::in(ReturnCondition::values())],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shipment = $this->route('shipment');
            $order = $shipment?->order;

            if ($shipment === null || $order === null) {
                $validator->errors()->add('shipment', 'Shipment must exist.');
                return;
            }

            $items = collect($this->input('items', []));
            $orderItems = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereIn('id', $items->pluck('order_item_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $existingCancelledQuantities = ShipmentItemResult::query()
                ->selectRaw('order_item_id, SUM(quantity) as total_quantity')
                ->whereIn('order_item_id', $orderItems->keys())
                ->where('result_status', ShipmentItemResultStatus::CANCELLED)
                ->groupBy('order_item_id')
                ->pluck('total_quantity', 'order_item_id');

            $requestedQuantities = [];

            foreach ($items as $index => $item) {
                $orderItemId = (int) ($item['order_item_id'] ?? 0);
                $resultStatus = $item['result_status'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 0);

                if (! $orderItems->has($orderItemId)) {
                    $validator->errors()->add("items.$index.order_item_id", 'The selected order item does not belong to this shipment order.');
                    continue;
                }

                if ($resultStatus === ShipmentItemResultStatus::RETURNED && blank($item['return_condition'] ?? null)) {
                    $validator->errors()->add("items.$index.return_condition", 'The return condition field is required when result status is returned.');
                }

                $orderItem = $orderItems->get($orderItemId);
                $existingCancelled = (int) ($existingCancelledQuantities[$orderItemId] ?? 0);
                $remainingQuantity = (int) $orderItem->quantity
                    - (int) $orderItem->delivered_quantity
                    - (int) $orderItem->returned_quantity
                    - (int) $orderItem->damaged_quantity
                    - $existingCancelled;

                $requestedQuantities[$orderItemId] = ($requestedQuantities[$orderItemId] ?? 0) + $quantity;

                if ($requestedQuantities[$orderItemId] > $remainingQuantity) {
                    $validator->errors()->add("items.$index.quantity", 'The quantity cannot exceed the remaining quantity for this item.');
                }
            }
        });
    }
}
