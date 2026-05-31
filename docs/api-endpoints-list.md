# API Endpoints List

This list mirrors the currently registered API route definitions from `routes/api.php` and `app/Modules/*/Routes/api.php`.

Operational notes:

- `POST /api/auth/login` is registered with `throttle:auth-login`
- `GET /api/track/{shipment_number}` is registered with `throttle:public-tracking`
- `/api/integrations/*` token routes are registered with `throttle:integration-api`

## Public Routes

GET /api/auth/ping
POST /api/auth/login
GET /api/public/settings
GET /api/track/{shipment_number}

## Protected Internal/Admin Routes

These routes require `auth:sanctum` unless noted otherwise, plus route-level permission middleware where configured.

### Auth And Current User

GET /api/auth/me
POST /api/auth/logout
GET /api/user

### User Management

GET /api/users
POST /api/users
GET /api/users/{user}
PUT /api/users/{user}
DELETE /api/users/{user}
POST /api/users/{user}/assign-role

### Roles And Permissions

GET /api/roles
POST /api/roles
GET /api/roles/{role}
PUT /api/roles/{role}
DELETE /api/roles/{role}
POST /api/roles/{role}/permissions
GET /api/permissions
GET /api/permissions/grouped

### Settings

GET /api/settings
GET /api/settings/groups
GET /api/settings/group/{group}
GET /api/settings/{setting}
PUT /api/settings/bulk-update
PUT /api/settings/{setting}

### Audit

GET /api/audit-logs
GET /api/audit-logs/timeline

### Dashboard

GET /api/dashboard/summary

### Merchants

GET /api/merchants
POST /api/merchants
GET /api/merchants/{merchant}
PUT /api/merchants/{merchant}
DELETE /api/merchants/{merchant}
GET /api/merchants/{merchant}/dashboard/summary

### Merchant Finance And Wallet

GET /api/merchants/{merchant}/finance/summary
GET /api/merchants/{merchant}/wallet/summary
GET /api/merchants/{merchant}/wallet/transactions
POST /api/merchants/{merchant}/wallet/deposit
POST /api/merchants/{merchant}/wallet/withdraw
GET /api/merchant-wallet-transactions/{transaction}
POST /api/merchant-wallet-transactions/{transaction}/approve
POST /api/merchant-wallet-transactions/{transaction}/reject

### Merchant Integration Management

GET /api/merchants/{merchant}/integration-tokens
POST /api/merchants/{merchant}/integration-tokens
GET /api/merchant-integration-tokens/{token}
POST /api/merchant-integration-tokens/{token}/revoke
DELETE /api/merchant-integration-tokens/{token}

### Merchant Portal User Management

GET /api/merchants/{merchant}/portal-users
POST /api/merchants/{merchant}/portal-users
GET /api/merchants/{merchant}/portal-users/{merchantUser}
PUT /api/merchants/{merchant}/portal-users/{merchantUser}
DELETE /api/merchants/{merchant}/portal-users/{merchantUser}
POST /api/merchants/{merchant}/portal-users/{merchantUser}/suspend
POST /api/merchants/{merchant}/portal-users/{merchantUser}/activate

### Merchant Inbound Request Admin

GET /api/merchant-inbound-stock-requests
GET /api/merchant-inbound-stock-requests/{inboundRequest}
POST /api/merchant-inbound-stock-requests/{inboundRequest}/approve
POST /api/merchant-inbound-stock-requests/{inboundRequest}/reject
POST /api/merchant-inbound-stock-requests/{inboundRequest}/link-warehouse-receipt

### Locations And Pricing

GET /api/governorates
POST /api/governorates
GET /api/governorates/{governorate}
PUT /api/governorates/{governorate}
DELETE /api/governorates/{governorate}
GET /api/areas
POST /api/areas
GET /api/areas/{area}
PUT /api/areas/{area}
DELETE /api/areas/{area}
GET /api/shipping-rates
POST /api/shipping-rates
POST /api/shipping-rates/calculate
GET /api/shipping-rates/{shippingRate}
PUT /api/shipping-rates/{shippingRate}
DELETE /api/shipping-rates/{shippingRate}
GET /api/pricing-plans
POST /api/pricing-plans
GET /api/pricing-plans/{pricingPlan}
PUT /api/pricing-plans/{pricingPlan}
DELETE /api/pricing-plans/{pricingPlan}
GET /api/pricing-plan-rates
POST /api/pricing-plan-rates
GET /api/pricing-plan-rates/{pricingPlanRate}
PUT /api/pricing-plan-rates/{pricingPlanRate}
DELETE /api/pricing-plan-rates/{pricingPlanRate}

### Orders

GET /api/orders
POST /api/orders
GET /api/orders/{order}
PUT /api/orders/{order}
DELETE /api/orders/{order}
POST /api/orders/{order}/confirm
GET /api/orders/{order}/label
POST /api/orders/{order}/shipments

### Shipments And Delivery Attempts

GET /api/shipments
GET /api/shipments/print-list
GET /api/shipments/{shipment}
PATCH /api/shipments/{shipment}/status
POST /api/shipments/{shipment}/complete-delivery
POST /api/shipments/{shipment}/assign-driver
POST /api/shipments/{shipment}/delivery-attempt
GET /api/delivery-attempts
GET /api/shipments/{shipment}/label
GET /api/shipments/{shipment}/waybill
POST /api/shipments/labels/bulk

### Drivers And Manifests

GET /api/drivers
POST /api/drivers
GET /api/drivers/{driver}
PUT /api/drivers/{driver}
DELETE /api/drivers/{driver}
GET /api/drivers/{driver}/manifest
GET /api/drivers/{driver}/shipments
GET /api/drivers/{driver}/cash-expected
POST /api/drivers/{driver}/cash-closures/generate
GET /api/driver-manifests
POST /api/driver-manifests/generate
GET /api/driver-manifests/{manifest}
POST /api/driver-manifests/{manifest}/close
GET /api/driver-manifests/{manifest}/print

### Barcode And Labels

POST /api/barcodes/lookup
POST /api/barcodes/scan/shipment-status
POST /api/barcodes/scan/product-lookup
GET /api/labels/templates
GET /api/labels/settings
POST /api/labels/preview
POST /api/labels/test
GET /api/warehouse-products/{warehouseProduct}/label
POST /api/warehouse-products/labels/bulk
GET /api/warehouse-bins/{warehouseBin}/label

### Exports

GET /api/exports
POST /api/exports/{type}
GET /api/exports/{exportLog}
GET /api/exports/{exportLog}/download

### Attachments

GET /api/attachments
POST /api/attachments
GET /api/attachments/{attachment}
GET /api/attachments/{attachment}/download
DELETE /api/attachments/{attachment}

### Finance

GET /api/driver-cash-closures
POST /api/driver-cash-closures
GET /api/driver-cash-closures/{driverCashClosure}
PUT /api/driver-cash-closures/{driverCashClosure}
DELETE /api/driver-cash-closures/{driverCashClosure}
GET /api/expenses
POST /api/expenses
GET /api/expenses/{expense}
PUT /api/expenses/{expense}
DELETE /api/expenses/{expense}
POST /api/expenses/{expense}/approve
POST /api/expenses/{expense}/reject
POST /api/expenses/{expense}/mark-paid
GET /api/merchant-payouts
POST /api/merchant-payouts
GET /api/merchant-payouts/{merchantPayout}
PUT /api/merchant-payouts/{merchantPayout}
DELETE /api/merchant-payouts/{merchantPayout}
POST /api/merchant-payouts/{merchantPayout}/approve
POST /api/merchant-payouts/{merchantPayout}/reject
POST /api/merchant-payouts/{merchantPayout}/complete
GET /api/merchant-invoices
POST /api/merchant-invoices
GET /api/merchant-invoices/{merchantInvoice}
PUT /api/merchant-invoices/{merchantInvoice}
DELETE /api/merchant-invoices/{merchantInvoice}
GET /api/merchant-invoices/{merchantInvoice}/preview
GET /api/merchant-invoices/{merchantInvoice}/download
GET /api/finance/company-profit-summary
GET /api/finance/reconciliation-summary
GET /api/finance/reports/overview
GET /api/finance/reports/merchant/{merchant}
GET /api/finance/reports/drivers
GET /api/finance/reports/expenses
GET /api/finance/reports/payouts
GET /api/finance/reports/daily-cash
GET /api/finance/reports/merchant-ledger
GET /api/finance/reports/driver-discrepancies
GET /api/finance/reports/warehouse-revenue
GET /api/finance/reports/profit-loss
GET /api/treasury-accounts
POST /api/treasury-accounts
GET /api/treasury-accounts/{treasuryAccount}
PUT /api/treasury-accounts/{treasuryAccount}
DELETE /api/treasury-accounts/{treasuryAccount}
GET /api/treasury-transactions

### Warehouses, Inventory, And Fulfillment

GET /api/warehouses
POST /api/warehouses
GET /api/warehouses/{warehouse}
PUT /api/warehouses/{warehouse}
DELETE /api/warehouses/{warehouse}
GET /api/warehouse-products
GET /api/warehouse-products/low-stock
POST /api/warehouse-products
POST /api/warehouse-products/barcode-labels/bulk
GET /api/warehouse-products/{warehouseProduct}
POST /api/warehouse-products/{warehouseProduct}/generate-barcode
GET /api/warehouse-products/{warehouseProduct}/barcode
GET /api/warehouse-products/{warehouseProduct}/barcode-label
PUT /api/warehouse-products/{warehouseProduct}
DELETE /api/warehouse-products/{warehouseProduct}
GET /api/warehouse-receipts
POST /api/warehouse-receipts
GET /api/warehouse-receipts/{warehouseReceipt}
PUT /api/warehouse-receipts/{warehouseReceipt}
POST /api/warehouse-receipts/{warehouseReceipt}/confirm
POST /api/warehouse-receipts/{warehouseReceipt}/cancel
GET /api/warehouse-transfers
POST /api/warehouse-transfers
GET /api/warehouse-transfers/{warehouseTransfer}
PUT /api/warehouse-transfers/{warehouseTransfer}
POST /api/warehouse-transfers/{warehouseTransfer}/confirm
POST /api/warehouse-transfers/{warehouseTransfer}/dispatch
POST /api/warehouse-transfers/{warehouseTransfer}/receive
POST /api/warehouse-transfers/{warehouseTransfer}/cancel
GET /api/warehouse-zones
POST /api/warehouse-zones
GET /api/warehouse-zones/{warehouseZone}
PUT /api/warehouse-zones/{warehouseZone}
DELETE /api/warehouse-zones/{warehouseZone}
GET /api/warehouse-shelves
POST /api/warehouse-shelves
GET /api/warehouse-shelves/{warehouseShelf}
PUT /api/warehouse-shelves/{warehouseShelf}
DELETE /api/warehouse-shelves/{warehouseShelf}
GET /api/warehouse-bins
POST /api/warehouse-bins
GET /api/warehouse-bins/{warehouseBin}
PUT /api/warehouse-bins/{warehouseBin}
DELETE /api/warehouse-bins/{warehouseBin}
GET /api/inventory-stocks
GET /api/inventory-stocks/low-stock
POST /api/inventory-stocks/adjust
GET /api/inventory-stocks/{inventoryStock}
GET /api/stock-movements
GET /api/stock-movements/{stockMovement}
GET /api/warehouse-returns
POST /api/warehouse-returns
GET /api/warehouse-returns/{warehouseReturn}
PATCH /api/warehouse-returns/{warehouseReturn}/status
POST /api/warehouse-returns/{warehouseReturn}/receive
POST /api/warehouse-returns/{warehouseReturn}/inspect
POST /api/warehouse-returns/{warehouseReturn}/restock
POST /api/warehouse-returns/{warehouseReturn}/mark-damaged
POST /api/warehouse-returns/{warehouseReturn}/reject
POST /api/warehouse-returns/{warehouseReturn}/close
GET /api/warehouse-charges
POST /api/warehouse-charges
GET /api/warehouse-charges/{warehouseCharge}
PUT /api/warehouse-charges/{warehouseCharge}
DELETE /api/warehouse-charges/{warehouseCharge}
GET /api/picking-lists
POST /api/picking-lists/generate
GET /api/picking-lists/{pickingList}
POST /api/picking-lists/{pickingList}/scan-pick
POST /api/picking-lists/{pickingList}/cancel
GET /api/packing-tasks
POST /api/packing-tasks/generate
GET /api/packing-tasks/{packingTask}
POST /api/packing-tasks/{packingTask}/complete
GET /api/storage-fee-rules
POST /api/storage-fee-rules
GET /api/storage-fee-rules/{storageFeeRule}
PUT /api/storage-fee-rules/{storageFeeRule}
DELETE /api/storage-fee-rules/{storageFeeRule}
GET /api/pick-pack-fee-rules
POST /api/pick-pack-fee-rules
GET /api/pick-pack-fee-rules/{pickPackFeeRule}
PUT /api/pick-pack-fee-rules/{pickPackFeeRule}
DELETE /api/pick-pack-fee-rules/{pickPackFeeRule}
POST /api/storage-fees/generate
POST /api/pick-pack-fees/generate
GET /api/warehouse/kpis
POST /api/warehouse/barcode/scan
POST /api/orders/{order}/reserve-stock
POST /api/orders/{order}/release-stock
POST /api/orders/{order}/warehouse/start-picking
POST /api/orders/{order}/warehouse/mark-picked
POST /api/orders/{order}/warehouse/start-packing
POST /api/orders/{order}/warehouse/mark-packed
POST /api/orders/{order}/warehouse/ready-for-dispatch
POST /api/orders/{order}/fulfill-from-warehouse
GET /api/orders/{order}/stock-reservations
GET /api/stock-reservations
GET /api/merchants/{merchant}/warehouse/inventory
GET /api/merchants/{merchant}/warehouse/movements
GET /api/merchants/{merchant}/warehouse/charges

## Protected Merchant Portal Routes

These routes require `auth:sanctum`, the `merchant.portal` middleware, and merchant capability checks in the controller layer.

### Context, Profile, And Settings

GET /api/merchant-portal/me
GET /api/merchant-portal/profile
PUT /api/merchant-portal/profile
GET /api/merchant-portal/settings
PUT /api/merchant-portal/settings
GET /api/merchant-portal/dashboard/summary

### Orders

GET /api/merchant-portal/orders
POST /api/merchant-portal/orders
GET /api/merchant-portal/orders/{order}
PUT /api/merchant-portal/orders/{order}
POST /api/merchant-portal/orders/{order}/confirm
POST /api/merchant-portal/orders/{order}/cancel
GET /api/merchant-portal/orders/{order}/label

### Shipments

GET /api/merchant-portal/shipments
GET /api/merchant-portal/shipments/{shipment}
GET /api/merchant-portal/shipments/{shipment}/tracking
GET /api/merchant-portal/shipments/{shipment}/label
GET /api/merchant-portal/shipments/{shipment}/waybill

### Warehouse

POST /api/merchant-portal/warehouse/products/labels/bulk
GET /api/merchant-portal/warehouse/products
GET /api/merchant-portal/warehouse/products/{warehouseProduct}
GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label
GET /api/merchant-portal/warehouse/inventory
GET /api/merchant-portal/warehouse/inventory/low-stock
GET /api/merchant-portal/warehouse/inventory/{inventoryStock}
GET /api/merchant-portal/warehouse/movements
GET /api/merchant-portal/warehouse/charges
GET /api/merchant-portal/warehouse/inbound-requests
POST /api/merchant-portal/warehouse/inbound-requests
GET /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}
POST /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}/cancel

### Finance

GET /api/merchant-portal/finance/summary
GET /api/merchant-portal/finance/wallet/summary
GET /api/merchant-portal/finance/wallet/transactions
GET /api/merchant-portal/finance/wallet/transactions/{transaction}
GET /api/merchant-portal/finance/invoices
GET /api/merchant-portal/finance/invoices/{invoice}
GET /api/merchant-portal/finance/invoices/{invoice}/preview
GET /api/merchant-portal/finance/invoices/{invoice}/download
GET /api/merchant-portal/finance/payouts
GET /api/merchant-portal/finance/payouts/{payout}
POST /api/merchant-portal/finance/payouts/request

### Integration Tokens

GET /api/merchant-portal/integration-tokens
POST /api/merchant-portal/integration-tokens
GET /api/merchant-portal/integration-tokens/{token}
POST /api/merchant-portal/integration-tokens/{token}/revoke
DELETE /api/merchant-portal/integration-tokens/{token}
GET /api/merchant-portal/integrations/docs
POST /api/merchant-portal/integrations/test

### Exports

GET /api/merchant-portal/exports/types
POST /api/merchant-portal/exports
GET /api/merchant-portal/exports
GET /api/merchant-portal/exports/{export}
GET /api/merchant-portal/exports/{export}/download

### Attachments

GET /api/merchant-portal/attachments
POST /api/merchant-portal/attachments
GET /api/merchant-portal/attachments/{attachment}
GET /api/merchant-portal/attachments/{attachment}/download
DELETE /api/merchant-portal/attachments/{attachment}

### Notifications

GET /api/merchant-portal/notifications
GET /api/merchant-portal/notifications/unread-count
POST /api/merchant-portal/notifications/{notification}/mark-read
POST /api/merchant-portal/notifications/mark-all-read

## Integration Token Routes

These routes require the `merchant.integration.token` middleware. Merchant scope is derived from the token, not from request payload.

GET /api/integrations/me
POST /api/integrations/orders
