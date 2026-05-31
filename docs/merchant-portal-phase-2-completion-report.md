# Merchant Portal Phase 2 Completion Report

## Summary

Merchant Portal Phase 2 adds merchant-facing Orders and Shipments APIs on top of Merchant Portal Phase 1 without changing the existing internal admin APIs, Sanctum login flow, or cross-module architecture.

The new portal layer:

- scopes all portal order and shipment access to the authenticated merchant membership
- rejects `merchant_id` overrides
- reuses existing order creation, confirmation, shipment, tracking, and label logic
- keeps shipment actions read-only for merchants
- adds a safe merchant-facing order cancellation path

## New APIs

- `GET /api/merchant-portal/orders`
- `POST /api/merchant-portal/orders`
- `GET /api/merchant-portal/orders/{order}`
- `PUT /api/merchant-portal/orders/{order}`
- `POST /api/merchant-portal/orders/{order}/confirm`
- `POST /api/merchant-portal/orders/{order}/cancel`
- `GET /api/merchant-portal/orders/{order}/label`
- `GET /api/merchant-portal/shipments`
- `GET /api/merchant-portal/shipments/{shipment}`
- `GET /api/merchant-portal/shipments/{shipment}/tracking`
- `GET /api/merchant-portal/shipments/{shipment}/label`
- `GET /api/merchant-portal/shipments/{shipment}/waybill`

## Security And Scoping Rules

- all portal routes use `auth:sanctum` and `merchant.portal`
- merchant context comes only from the authenticated merchant portal membership
- `merchant_id` in portal query string or request body is rejected with `422`
- order and shipment lookups are merchant-scoped and return `404` for cross-merchant access
- portal capabilities are enforced through `MerchantPortalCapabilityService`
- shipment mutation stays internal-only

## Merchant Order Flow

1. Merchant portal user authenticates with existing Sanctum login.
2. Merchant portal order list returns only orders for the current merchant.
3. Merchant portal order create injects `merchant_id` from context and stores `source = manual`.
4. Merchant portal order update reuses existing lifecycle lock rules.
5. Merchant portal order confirm reuses existing confirm action.
6. Merchant portal order cancel is allowed only before shipment creation and before non-releasable warehouse processing starts.
7. If active reserved stock exists, it is released before the order is marked cancelled.
8. Merchant can print only labels for owned orders.

## Merchant Shipment Flow

1. Merchant portal shipment list returns only shipments for the current merchant.
2. Merchant can view shipment detail and merchant-safe tracking.
3. Merchant can print owned shipment labels and waybills through the existing Label module.
4. Merchant cannot assign drivers, update shipment status, complete delivery, or create delivery attempts from the portal.

## Supported Filters

Orders:

- `date_from`
- `date_to`
- `status`
- `fulfillment_type`
- `payment_type`
- `order_type`
- `customer_phone`
- `order_number`
- `search`
- `has_shipment`
- `warehouse_fulfillment_status`
- `delivery_governorate_id`
- `delivery_area_id`

Shipments:

- `date_from`
- `date_to`
- `status`
- `shipment_number`
- `search`
- `customer_phone`
- `payment_type`
- `cod_only`
- `delivery_governorate_id`
- `delivery_area_id`
- `has_driver`

Forbidden portal inputs:

- `merchant_id`
- `source`
- `status`
- internal shipment mutation fields
- `driver_id`
- `assigned_driver_id`

## Files Added

- `app/Modules/MerchantPortal/Controllers/MerchantPortalOrderController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalShipmentController.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalOrdersRequest.php`
- `app/Modules/MerchantPortal/Requests/StoreMerchantPortalOrderRequest.php`
- `app/Modules/MerchantPortal/Requests/UpdateMerchantPortalOrderRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalShipmentsRequest.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalOrderResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalOrderDetailResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalShipmentResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalShipmentDetailResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalTrackingResource.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalOrderService.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalShipmentService.php`
- `tests/Feature/MerchantPortalPhaseTwoTest.php`

## Files Modified

- `app/Modules/MerchantPortal/Routes/api.php`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`

## Tests Added

- `tests/Feature/MerchantPortalPhaseTwoTest.php`

Coverage includes:

- authentication and membership enforcement
- merchant context isolation
- cross-merchant order and shipment protection
- order create/update/confirm/cancel portal rules
- label access capability checks
- shipment read-only behavior
- capability enforcement by merchant portal role
- regression check for existing internal order and shipment APIs

## Manual Commands To Run

```powershell
php artisan test --filter=MerchantPortalPhaseTwoTest
php artisan test --filter=MerchantPortalPhaseOneTest
php artisan test --filter=OrderShipmentFlowTest
php artisan test --filter=LabelPrintingFlowTest
php artisan test
```

## Future Phases

- merchant-facing warehouse visibility endpoints
- merchant-facing finance, invoices, and payouts
- merchant integrations management inside portal
- optional merchant profile update endpoint with a stricter editable-field boundary
- optional merchant-facing shipment documents beyond label and waybill
