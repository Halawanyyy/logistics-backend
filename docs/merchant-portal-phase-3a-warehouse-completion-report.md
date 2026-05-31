# Merchant Portal Phase 3A Warehouse Completion Report

## Summary

Merchant Portal Phase 3A adds merchant-facing warehouse visibility and inbound stock request APIs on top of the existing Warehouse, Inventory, Label, and Merchant Portal foundations.

Implemented in this phase:

- merchant-scoped warehouse product list, detail, and label printing
- merchant-scoped bulk warehouse product labels
- merchant-scoped inventory list, detail, and low-stock view
- merchant-scoped stock movement list
- merchant-scoped warehouse charge list
- merchant-scoped inbound stock request create, list, detail, and cancel
- minimal internal inbound request review endpoints for approve, reject, and link to warehouse receipt

## New APIs

Merchant portal warehouse endpoints:

- `GET /api/merchant-portal/warehouse/products`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label`
- `POST /api/merchant-portal/warehouse/products/labels/bulk`
- `GET /api/merchant-portal/warehouse/inventory`
- `GET /api/merchant-portal/warehouse/inventory/low-stock`
- `GET /api/merchant-portal/warehouse/inventory/{inventoryStock}`
- `GET /api/merchant-portal/warehouse/movements`
- `GET /api/merchant-portal/warehouse/charges`
- `GET /api/merchant-portal/warehouse/inbound-requests`
- `POST /api/merchant-portal/warehouse/inbound-requests`
- `GET /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}`
- `POST /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}/cancel`

Internal operational endpoints:

- `GET /api/merchant-inbound-stock-requests`
- `GET /api/merchant-inbound-stock-requests/{inboundRequest}`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/approve`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/reject`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/link-warehouse-receipt`

## Security And Scoping Rules

- all merchant warehouse endpoints require `auth:sanctum` and `merchant.portal`
- merchant context comes only from the authenticated merchant portal membership
- `merchant_id` is rejected with `422` on merchant portal warehouse requests
- cross-merchant resource access returns `404` through merchant-scoped queries
- capability checks are enforced through `MerchantPortalAccessService`
- merchant portal cannot:
  - adjust stock
  - confirm warehouse receipts
  - create warehouse charges
  - dispatch or receive warehouse transfers
  - mutate internal warehouse stock directly

## Merchant Warehouse Product Flow

1. merchant lists only its own warehouse products
2. merchant can inspect product stock summary and recent movement summary
3. merchant can print single or bulk product labels in the existing label formats

## Merchant Inventory Flow

1. merchant lists inventory rows through merchant-owned warehouse products
2. low-stock endpoint reuses the existing inventory low-stock logic
3. inventory remains read-only from merchant portal

## Merchant Inbound Stock Request Flow

1. merchant submits inbound stock request
2. request status starts as `submitted`
3. request does not change inventory or create stock movements
4. internal warehouse staff reviews the request
5. internal staff can approve, reject, and link a real warehouse receipt
6. warehouse receipt confirmation remains the actual stock-in authority
7. once linked to a confirmed receipt, the inbound request can move to `received` or `partially_received`

## What Increases Stock And What Does Not

Does not increase stock:

- merchant inbound stock request creation
- merchant inbound stock request cancellation
- linking an inbound request to an unconfirmed warehouse receipt

Does increase stock:

- internal warehouse receipt confirmation through the existing warehouse receipt flow

## Supported Filters

Warehouse products:

- `search`
- `sku`
- `barcode`
- `status`
- `low_stock`
- `warehouse_id`
- `has_stock`
- `created_from`
- `created_to`

Inventory:

- `warehouse_id`
- `warehouse_product_id`
- `sku`
- `barcode`
- `low_stock`
- `has_reserved`
- `available_only`
- `search`

Stock movements:

- `warehouse_id`
- `warehouse_product_id`
- `movement_type`
- `reference_type`
- `reference_id`
- `order_id`
- `date_from`
- `date_to`
- `sku`
- `barcode`

Warehouse charges:

- `date_from`
- `date_to`
- `charge_type`
- `status`
- `warehouse_id`
- `search`

Inbound stock requests:

- `status`
- `request_number`
- `date_from`
- `date_to`
- `warehouse_id`
- `expected_arrival_from`
- `expected_arrival_to`

## Response Examples

Inbound request list item:

```json
{
  "id": 9,
  "request_number": "MIR-20260531-000009",
  "status": "submitted",
  "warehouse_summary": {
    "id": 2,
    "name": "Main Warehouse"
  },
  "items_count": 2,
  "total_requested_quantity": 70,
  "expected_arrival_date": "2026-06-10",
  "submitted_at": "2026-05-31T14:00:00.000000Z",
  "cancelled_at": null,
  "received_at": null,
  "closed_at": null,
  "created_at": "2026-05-31T14:00:00.000000Z"
}
```

## Files Added

- `app/Modules/MerchantPortal/Controllers/MerchantPortalWarehouseProductController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalInventoryController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalStockMovementController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalWarehouseChargeController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalInboundStockRequestController.php`
- `app/Modules/MerchantPortal/Controllers/MerchantInboundStockRequestAdminController.php`
- `app/Modules/MerchantPortal/Enums/MerchantInboundStockRequestStatus.php`
- `app/Modules/MerchantPortal/Models/MerchantInboundStockRequest.php`
- `app/Modules/MerchantPortal/Models/MerchantInboundStockRequestItem.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalWarehouseProductsRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalInventoryRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalStockMovementsRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalWarehouseChargesRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantPortalInboundRequestsRequest.php`
- `app/Modules/MerchantPortal/Requests/StoreMerchantPortalInboundRequestRequest.php`
- `app/Modules/MerchantPortal/Requests/BulkMerchantPortalWarehouseProductLabelsRequest.php`
- `app/Modules/MerchantPortal/Requests/ListMerchantInboundStockRequestsAdminRequest.php`
- `app/Modules/MerchantPortal/Requests/ReviewMerchantInboundStockRequestRequest.php`
- `app/Modules/MerchantPortal/Requests/LinkMerchantInboundStockRequestReceiptRequest.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalWarehouseProductResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalWarehouseProductDetailResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalInventoryStockResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalStockMovementResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalWarehouseChargeResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalInboundStockRequestResource.php`
- `app/Modules/MerchantPortal/Resources/MerchantPortalInboundStockRequestDetailResource.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalWarehouseService.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalInboundStockRequestService.php`
- `database/migrations/2026_05_31_100001_create_merchant_inbound_stock_requests_tables.php`
- `tests/Feature/MerchantPortalPhaseThreeWarehouseTest.php`
- `docs/merchant-portal-phase-3a-warehouse-completion-report.md`

## Files Modified

- `app/Modules/MerchantPortal/Routes/api.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalCapabilityService.php`
- `app/Modules/RolePermission/Support/RolePermissionDefaults.php`
- `tests/Feature/Concerns/InteractsWithPlatform.php`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`

## Tests Added

- `tests/Feature/MerchantPortalPhaseThreeWarehouseTest.php`

Coverage focus:

- auth and active merchant membership
- merchant-only warehouse product, inventory, movement, charge, and inbound request scoping
- `merchant_id` rejection
- product label and bulk label scoping
- inbound request creation without stock increase
- inbound request creation without stock movements
- inbound request cancellation rules
- capability enforcement for warehouse viewers, accountants, and label printing

## Manual Commands To Run

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan test --filter=MerchantPortalPhaseThreeWarehouseTest
php artisan test --filter=MerchantPortalPhaseOneTest
php artisan test --filter=MerchantPortalPhaseTwoTest
php artisan test --filter=WarehousePhaseTwoTest
php artisan test --filter=LabelPrintingFlowTest
php artisan test
```

## Future Phases

- merchant-facing warehouse receipts visibility
- merchant-triggered SKU onboarding workflow for new requested SKUs
- richer inbound request timeline and notifications
- merchant warehouse returns visibility
- merchant warehouse finance and invoice integration
