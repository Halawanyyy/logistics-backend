<?php

$validation = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

$validation['custom'] = array_replace_recursive($validation['custom'] ?? [], [
    'delivery_area_id' => [
        'belongs_to_governorate' => 'The selected delivery area does not belong to the selected delivery governorate.',
    ],
    'pickup_governorate_id' => [
        'required_with_pickup_area' => 'The pickup governorate field is required when pickup area is present.',
    ],
    'pickup_area_id' => [
        'belongs_to_governorate' => 'The selected pickup area does not belong to the selected pickup governorate.',
    ],
    'fulfillment_type' => [
        'return_orders_only' => 'Return orders must use pickup_from_customer fulfillment type.',
        'pickup_from_customer_only_for_returns' => 'The pickup_from_customer fulfillment type is only allowed for return orders.',
    ],
    'original_order_id' => [
        'only_for_swap_orders' => 'The original order id field is only allowed for swap orders.',
        'must_belong_to_current_merchant' => 'The selected original order does not belong to the current merchant.',
    ],
    'driver_id' => [
        'inactive' => 'Selected driver is inactive.',
    ],
]);

$validation['attributes'] = array_replace($validation['attributes'] ?? [], [
    'name' => 'name',
    'email' => 'email address',
    'password' => 'password',
    'phone' => 'phone',
    'status' => 'status',
    'role' => 'role',
    'notes' => 'notes',
    'created_at' => 'created at',
    'updated_at' => 'updated at',
    'customer_name' => 'customer name',
    'customer_phone' => 'customer phone',
    'delivery_address' => 'delivery address',
    'delivery_governorate_id' => 'delivery governorate',
    'delivery_area_id' => 'delivery area',
    'payment_type' => 'payment type',
    'fulfillment_type' => 'fulfillment type',
    'cod_amount' => 'COD amount',
    'items' => 'items',
    'items.*.product_name' => 'product name',
    'items.*.quantity' => 'quantity',
    'items.*.unit_price' => 'unit price',
    'shipment_number' => 'shipment number',
    'driver_id' => 'driver',
    'delivery_date' => 'delivery date',
    'warehouse_id' => 'warehouse',
    'warehouse_product_id' => 'warehouse product',
    'sku' => 'SKU',
    'barcode' => 'barcode',
    'quantity' => 'quantity',
    'available_quantity' => 'available quantity',
    'reserved_quantity' => 'reserved quantity',
    'damaged_quantity' => 'damaged quantity',
    'inbound_request_id' => 'inbound request',
    'amount' => 'amount',
    'invoice_number' => 'invoice number',
    'payout_number' => 'payout number',
    'payment_method' => 'payment method',
    'bank_name' => 'bank name',
    'bank_account_number' => 'bank account number',
    'merchant_id' => 'merchant',
    'portal_role' => 'portal role',
    'portal_status' => 'portal status',
    'file' => 'file',
    'attachment' => 'attachment',
    'context' => 'context',
]);

return $validation;
