<?php

namespace App\Modules\Shipment\Actions;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\OrderLifecycleService;
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
        private readonly OrderLifecycleService $orderLifecycleService,
        private readonly SystemSettingService $systemSettingService,
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function execute(Order $order, ?int $userId): Shipment
    {
        $this->orderLifecycleService->ensureShipmentCanBeCreated($order);

        return DB::transaction(function () use ($order, $userId) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->orderLifecycleService->ensureShipmentCanBeCreated($lockedOrder);

            $configuredStatus = (string) $this->systemSettingService->get('default_shipment_status', ShipmentStatus::PENDING_PICKUP);
            $defaultStatus = in_array($configuredStatus, ShipmentStatus::values(), true)
                ? $configuredStatus
                : ShipmentStatus::PENDING_PICKUP;

            $shipment = Shipment::create([
                'order_id' => $lockedOrder->id,
                'shipment_number' => $this->generateShipmentNumberAction->execute(),
                'barcode' => $this->barcodeService->generateShipmentBarcode(),
                'merchant_id' => $lockedOrder->merchant_id,
                'customer_name' => $lockedOrder->customer_name,
                'customer_phone' => $lockedOrder->customer_phone,
                'delivery_governorate_id' => $lockedOrder->delivery_governorate_id,
                'delivery_area_id' => $lockedOrder->delivery_area_id,
                'delivery_address' => $lockedOrder->delivery_address,
                'cod_amount' => $lockedOrder->cod_amount,
                'shipping_fee' => $lockedOrder->shipping_fee,
                'status' => $defaultStatus,
                'created_by' => $userId,
            ]);

            $shipment->histories()->create([
                'status' => $defaultStatus,
                'notes' => 'Shipment created from order.',
                'changed_by' => $userId,
            ]);

            $lockedOrder->update([
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
