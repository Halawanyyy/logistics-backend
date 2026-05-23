<?php

namespace App\Modules\Shipment\Actions;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Settings\Services\SystemSettingService;
use App\Services\BarcodeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class CreateShipmentFromOrderAction
{
    public function __construct(
        private readonly GenerateShipmentNumberAction $generateShipmentNumberAction,
        private readonly SystemSettingService $systemSettingService,
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function execute(Order $order, ?int $userId): Shipment
    {
        if ($order->status !== OrderStatus::CONFIRMED) {
            $this->fail('Only confirmed orders can be converted to shipment.');
        }

        if ($order->requires_review) {
            $this->fail('Order requires review before shipment creation.');
        }

        if (! $order->items()->exists()) {
            $this->fail('Order must have at least one item before shipment creation.');
        }

        if (Shipment::query()->where('order_id', $order->id)->exists()) {
            $this->fail('Shipment already exists for this order.');
        }

        return DB::transaction(function () use ($order, $userId) {
            if (Shipment::query()->lockForUpdate()->where('order_id', $order->id)->exists()) {
                $this->fail('Shipment already exists for this order.');
            }

            $configuredStatus = (string) $this->systemSettingService->get('default_shipment_status', ShipmentStatus::PENDING_PICKUP);
            $defaultStatus = in_array($configuredStatus, ShipmentStatus::values(), true)
                ? $configuredStatus
                : ShipmentStatus::PENDING_PICKUP;

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'shipment_number' => $this->generateShipmentNumberAction->execute(),
                'barcode' => $this->barcodeService->generateShipmentBarcode(),
                'merchant_id' => $order->merchant_id,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'delivery_governorate_id' => $order->delivery_governorate_id,
                'delivery_area_id' => $order->delivery_area_id,
                'delivery_address' => $order->delivery_address,
                'cod_amount' => $order->cod_amount,
                'shipping_fee' => $order->shipping_fee,
                'status' => $defaultStatus,
                'created_by' => $userId,
            ]);

            $shipment->histories()->create([
                'status' => $defaultStatus,
                'notes' => 'Shipment created from order.',
                'changed_by' => $userId,
            ]);

            $order->update([
                'status' => OrderStatus::SHIPMENT_CREATED,
            ]);

            return $shipment->fresh();
        });
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
