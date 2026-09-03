# Logistics & Warehouse ERP — Admin Frontend Flow Documentation

> Purpose: This document translates the backend API documentation into practical frontend flows, screens, actions, permissions, and integration notes for the Admin Dashboard team.
>
> Backend status: Admin backend core is completed and ready for Admin Frontend implementation. Merchant Portal is not required for Admin Frontend, but merchant integration tokens are ready for future WooCommerce / Shopify plugins.

---

## 1. System Overview

The backend is a Laravel 12 modular monolith for a Logistics, Warehouse, Fulfillment, and Finance ERP. It supports internal admin users, roles and permissions, merchants, shipping pricing, orders, shipments, drivers, warehouse operations, finance ledger/wallet, audit logs, settings, exports, attachments, barcodes, and integration tokens.

### Main Admin Areas

1. Auth & Session
2. Dashboard
3. Users
4. Roles & Permissions
5. Settings
6. Merchants
7. Merchant Integration Tokens
8. Locations & Pricing
9. Orders
10. Shipments
11. Drivers & Daily Manifests
12. Warehouse & Fulfillment
13. Finance
14. Barcode Operations
15. Audit Logs & Activity Timelines
16. Exports
17. Attachments

---

## 2. Frontend Architecture Recommendation

Recommended route structure:

```text
/admin/login
/admin/dashboard
/admin/users
/admin/roles
/admin/settings
/admin/merchants
/admin/pricing
/admin/orders
/admin/shipments
/admin/drivers
/admin/driver-manifests
/admin/warehouse
/admin/finance
/admin/audit-logs
/admin/exports
```

Prepare for future merchant portal without building it now:

```text
/admin/*
/merchant/*   future
```

Recommended frontend folders:

```text
/src
  /apps
    /admin
    /merchant    future placeholder
  /shared
    /api
    /components
    /hooks
    /layouts
    /utils
    /types
```

---

## 3. Auth Flow

### Screens

- Login
- Current user profile dropdown
- Unauthorized / 403 screen

### Flow

1. User opens `/admin/login`.
2. Frontend calls `POST /api/auth/login`.
3. Store Sanctum bearer token.
4. Call `GET /api/auth/me` to load user and permissions.
5. Route access depends on permissions.
6. Logout calls `POST /api/auth/logout` and clears local session.

### APIs

```text
POST /api/auth/login
GET /api/auth/me
POST /api/auth/logout
GET /api/auth/ping
```

### Frontend Notes

- Every protected request sends `Authorization: Bearer TOKEN`.
- If backend returns `403`, show permission denied.
- If backend returns `401`, redirect to login.

---

## 4. Dashboard Flow

### Screen

`/admin/dashboard`

### What to Show

- Company profit
- Merchants payable
- COD collected
- Shipments total
- Pending pickup
- In transit
- Delivered
- Assigned / unassigned shipments
- Optional filters: date range, merchant, driver

### API

```text
GET /api/dashboard/summary
```

### Filters

```text
date_from
date_to
merchant_id
assigned_driver_id
```

---

## 5. Users Module

### Screens

- Users list
- Create user
- Edit user
- User details

### What Admin Can Do

- View internal users
- Create users
- Update users
- Soft delete users
- Assign role to user

### APIs

```text
GET /api/users
POST /api/users
GET /api/users/{user}
PUT /api/users/{user}
DELETE /api/users/{user}
POST /api/users/{user}/assign-role
```

### Important UI Fields

```text
name
email
password
role / role_id
permissions readonly preview
```

---

## 6. Roles & Permissions Module

### Screens

- Roles list
- Create role
- Edit role
- Role details
- Permissions grouped by module

### What Admin Can Do

- Create custom roles
- Assign permissions to roles
- Update role status
- Delete non-system roles
- View permissions grouped by module

### APIs

```text
GET /api/roles
POST /api/roles
GET /api/roles/{role}
PUT /api/roles/{role}
DELETE /api/roles/{role}
POST /api/roles/{role}/permissions
GET /api/permissions
GET /api/permissions/grouped
```

### Frontend Notes

- Use grouped permissions to render checkbox groups per module.
- System roles should show as protected/locked.
- Super Admin should keep all permissions.

---

## 7. Settings Module

### Screens

- Settings groups page
- Company settings
- Finance settings
- Shipping settings
- Warehouse settings
- Barcode settings
- Notifications settings placeholders
- System / Security / Integrations settings

### What Admin Can Do

- View settings by group
- Update one setting
- Bulk update settings
- View public settings

### APIs

```text
GET /api/settings
GET /api/settings/groups
GET /api/settings/group/{group}
GET /api/settings/{setting}
PUT /api/settings/{setting}
PUT /api/settings/bulk-update
GET /api/public/settings
```

### Important Frontend Behavior

Each setting includes:

```text
group
key
value
type
label
description
options
validation_rules
is_public
is_encrypted
is_editable
sort_order
```

Use this to auto-render forms dynamically.

Encrypted settings show masked value only.

---

## 8. Merchants Module

### Screens

- Merchants list
- Create merchant
- Edit merchant
- Merchant profile
- Merchant dashboard tab
- Merchant finance tab
- Merchant wallet tab
- Merchant invoices tab
- Merchant warehouse stock tab
- Merchant integration tokens tab
- Merchant attachments tab

### What Admin Can Do

- CRUD merchants
- Assign pricing plan
- View merchant dashboard summary
- View finance summary
- View wallet and transactions
- Generate integration token for WooCommerce / Shopify plugin
- Upload merchant documents

### APIs

```text
GET /api/merchants
POST /api/merchants
GET /api/merchants/{merchant}
PUT /api/merchants/{merchant}
DELETE /api/merchants/{merchant}
GET /api/merchants/{merchant}/dashboard/summary
GET /api/merchants/{merchant}/finance/summary
GET /api/merchants/{merchant}/wallet/summary
GET /api/merchants/{merchant}/wallet/transactions
```

### Merchant Profile Recommended Tabs

```text
Overview
Orders
Shipments
Wallet
Invoices
Warehouse
Integration Tokens
Attachments
Activity Timeline
```

---

## 9. Merchant Integration Tokens

### Purpose

This is for future WooCommerce / Shopify plugin connection. The merchant does not need to give store passwords to the logistics company. Admin generates a secure token and gives it to the merchant/plugin.

### Screens

- Inside Merchant Profile → Integration Tokens tab
- Create token modal
- Revoke token action
- Token details

### Admin APIs

```text
GET /api/merchants/{merchant}/integration-tokens
POST /api/merchants/{merchant}/integration-tokens
GET /api/merchant-integration-tokens/{token}
POST /api/merchant-integration-tokens/{token}/revoke
DELETE /api/merchant-integration-tokens/{token}
```

### Plugin APIs for Future

```text
GET /api/integrations/me
POST /api/integrations/orders
```

### Frontend Notes

- Raw token is returned only once on creation.
- Show copy button immediately after creation.
- Never expect backend to show raw token again.
- Token permissions are separate from admin permissions.

Token permissions:

```text
orders.create
orders.view
shipments.view
tracking.view
products.view
inventory.view
```

---

## 10. Locations & Pricing Flow

### Screens

- Governorates
- Areas
- Legacy shipping rates
- Pricing plans
- Pricing plan rates

### Business Purpose

Shipping cost can be calculated using:

1. Merchant pricing plan rate by area
2. Merchant pricing plan governorate default
3. Legacy shipping rate fallback

Order types affect price:

```text
delivery → delivery_fee
swap → swap_fee
return → return_fee
```

### APIs

```text
GET/POST/GET one/PUT/DELETE /api/governorates
GET/POST/GET one/PUT/DELETE /api/areas
GET/POST/GET one/PUT/DELETE /api/shipping-rates
POST /api/shipping-rates/calculate
GET/POST/GET one/PUT/DELETE /api/pricing-plans
GET/POST/GET one/PUT/DELETE /api/pricing-plan-rates
```

### Frontend Notes

- Area dropdown should depend on selected governorate.
- Pricing plan rates need filters by plan, governorate, area.
- Merchant create/edit should include pricing plan selection.

---

## 11. Orders Module

### Screens

- Orders list
- Create order
- Edit order
- Order details
- Confirm order action
- Create shipment action
- Activity timeline tab
- Stock reservation tab/section when fulfillment type is from warehouse

### Order Types

```text
delivery: normal order
swap: deliver new item and collect old item
return: pickup returned item from customer
```

### Payment Types

```text
cod
prepaid
```

### Fulfillment Types

```text
pickup_from_merchant
from_warehouse
pickup_from_customer
```

### Main Flow

```text
Create order → Draft
Review order
Confirm order → Confirmed
Create shipment
Shipment lifecycle starts
```

### APIs

```text
GET /api/orders
POST /api/orders
GET /api/orders/{order}
PUT /api/orders/{order}
DELETE /api/orders/{order}
POST /api/orders/{order}/confirm
POST /api/orders/{order}/shipments
```

### Filters

```text
status
order_type
fulfillment_type
payment_type
delivery_status
partially_delivered
merchant_id
merchant_name
date_from
date_to
search
```

### Frontend Rules

- If `requires_review = true`, show review warning.
- If shipment exists and settings disallow edit/delete, hide or disable edit/delete.
- For `from_warehouse`, show reserve/fulfill/release stock actions.
- For `swap`, show original order and swap notes fields.
- For `return`, use pickup-from-customer logic.

---

## 12. Shipments Module

### Screens

- Shipments list
- Shipment details
- Assign driver
- Update status
- Complete delivery item-level
- Delivery attempt
- Printable shipment list
- Public tracking link preview
- Activity timeline

### Shipment Lifecycle

```text
pending_pickup
driver_assigned
picked_up
arrived_at_hub
sorting
ready_for_dispatch
out_for_delivery
delivery_attempted
rescheduled
delivered
partially_delivered
failed
returned_to_hub
returned_to_warehouse
cancelled
```

### APIs

```text
GET /api/shipments
GET /api/shipments/{shipment}
PATCH /api/shipments/{shipment}/status
POST /api/shipments/{shipment}/assign-driver
POST /api/shipments/{shipment}/complete-delivery
GET /api/track/{shipment_number}
GET /api/delivery-attempts
POST /api/shipments/{shipment}/delivery-attempt
```

### Filters

```text
status
order_type
partially_delivered
merchant_id
assigned_driver_id
delivery_governorate_id
delivery_area_id
date_from
date_to
search
```

### Complete Delivery UI

The frontend should let operations choose result per item:

```text
delivered
returned
damaged
cancelled
```

For returned/damaged warehouse items, include return condition:

```text
sellable
damaged
disposed
```

---

## 13. Drivers & Daily Manifests

### Screens

- Drivers list
- Create / edit driver
- Driver details
- Driver shipments
- Driver manifest print view
- Driver daily manifests list
- Generate manifest
- Manifest details
- Close manifest

### Business Flow

```text
Operations selects driver + date + shipments
Generate manifest
System moves shipments to out_for_delivery
Print manifest for driver
Driver returns end of day
Operations enters final shipment results
Close manifest
System calculates expected COD vs actual received
Cash closure remains pending
Finance or Admin verifies the cash closure
Finance can see discrepancy
```

### Driver APIs

```text
GET/POST/GET one/PUT/DELETE /api/drivers
GET /api/drivers/{driver}/shipments
GET /api/drivers/{driver}/manifest
GET /api/drivers/{driver}/cash-expected
```

### Manifest APIs

```text
POST /api/driver-manifests/generate
GET /api/driver-manifests
GET /api/driver-manifests/{manifest}
POST /api/driver-manifests/{manifest}/close
GET /api/driver-manifests/{manifest}/print
```

### Frontend Rules

- Cannot close a manifest until every shipment has status `delivered`.
- Actual received amount is entered manually by operations/finance.
- Difference is calculated by backend.
- Closing creates a pending cash closure; Finance or Admin must verify it before treasury synchronization.
- Manifest print endpoint is JSON for frontend print/PDF rendering.

---

## 14. Warehouse & Fulfillment Module

### Screens

- Warehouses
- Warehouse products
- Inventory stock
- Stock movements
- Stock reservations
- Warehouse returns
- Warehouse charges
- Warehouse zones
- Warehouse shelves
- Warehouse bins
- Picking lists
- Packing tasks
- Storage fee rules
- Pick & pack fee rules
- Warehouse KPIs

### Core Concepts

Inventory tracks:

```text
quantity_available
quantity_reserved
quantity_damaged
```

Warehouse stock is merchant-aware through warehouse products and inventory records.

### Main Warehouse Order Flow

```text
Order fulfillment_type = from_warehouse
Reserve stock
Fulfill from warehouse
Generate picking list
Scan picked products
Generate packing tasks
Complete packing
Create shipment / dispatch
Handle returns and stock restore if needed
```

### Warehouse Location APIs

```text
GET/POST /api/warehouse-zones
GET/POST /api/warehouse-shelves
GET/POST /api/warehouse-bins
```

### Picking APIs

```text
GET /api/picking-lists
POST /api/picking-lists/generate
POST /api/picking-lists/{pickingList}/scan-pick
POST /api/picking-lists/{pickingList}/cancel
```

### Packing APIs

```text
GET /api/packing-tasks
POST /api/packing-tasks/generate
POST /api/packing-tasks/{packingTask}/complete
```

### Billing APIs

```text
GET/POST /api/storage-fee-rules
POST /api/storage-fees/generate
GET/POST /api/pick-pack-fee-rules
POST /api/pick-pack-fees/generate
GET /api/warehouse/kpis
```

### Frontend Notes

- Barcode scan input can be a normal text input focused by default.
- Scanner acts like keyboard input.
- Storage fees and pick/pack fees create warehouse charges that finance can see.

---

## 15. Barcode Operations

### Screens

- Barcode lookup screen
- Shipment scan status update
- Product scan lookup
- Barcode display inside order/shipment/product/bin details

### APIs

```text
POST /api/barcodes/lookup
POST /api/barcodes/scan/shipment-status
POST /api/barcodes/scan/product-lookup
```

### Frontend Notes

- Use a single focused input for scanners.
- On Enter, submit scanned barcode.
- Show result type: order, shipment, warehouse product, bin.

---

## 16. Finance Module

### Screens

- Finance dashboard
- Merchant wallet summary
- Merchant wallet transactions
- Manual deposit
- Manual withdraw
- Approve/reject wallet transaction
- Merchant payouts
- Payout approval/complete flow
- Expenses
- Expense approval/payment flow
- Merchant invoices
- Treasury accounts
- Treasury transactions
- Reports
- Reconciliation summary

### Merchant Wallet Flow

```text
System creates credits/debits from operations
Admin can create manual deposit/withdraw
Pending transaction can be approved/rejected
Approved credits increase balance
Approved debits decrease balance
```

### Payout Flow

```text
Create payout → pending
Approve payout → approved
Complete payout → completed
Ledger debit created
Treasury debit created
```

### Expense Flow

```text
Create expense → pending
Approve expense → approved
Mark paid → paid
Treasury debit created
```

### APIs

```text
GET /api/merchants/{merchant}/wallet/summary
GET /api/merchants/{merchant}/wallet/transactions
POST /api/merchants/{merchant}/wallet/deposit
POST /api/merchants/{merchant}/wallet/withdraw
GET /api/merchant-wallet-transactions/{transaction}
POST /api/merchant-wallet-transactions/{transaction}/approve
POST /api/merchant-wallet-transactions/{transaction}/reject

GET/POST/GET one/PUT/DELETE /api/merchant-payouts
POST /api/merchant-payouts/{merchantPayout}/approve
POST /api/merchant-payouts/{merchantPayout}/reject
POST /api/merchant-payouts/{merchantPayout}/complete

GET/POST/GET one/PUT/DELETE /api/expenses
POST /api/expenses/{expense}/approve
POST /api/expenses/{expense}/reject
POST /api/expenses/{expense}/mark-paid

GET/POST/GET one/PUT/DELETE /api/merchant-invoices
GET /api/finance/reconciliation-summary
GET /api/finance/company-profit-summary

GET/POST/GET one/PUT/DELETE /api/treasury-accounts
GET /api/treasury-transactions

GET /api/finance/reports/daily-cash
GET /api/finance/reports/merchant-ledger
GET /api/finance/reports/driver-discrepancies
GET /api/finance/reports/warehouse-revenue
GET /api/finance/reports/profit-loss
```

### Frontend Notes

- Do not allow completed payout editing except supported backend actions.
- Payout complete should require treasury account when needed.
- Expense paid should require treasury account.
- Show legacy summary and wallet summary separately if both exist.

---

## 17. Audit Logs & Activity Timeline

### Screens

- Global Audit Logs
- Entity Timeline component reusable inside:
  - Order details
  - Shipment details
  - Merchant details
  - Wallet transaction details
  - Warehouse product details
  - Payout / expense details

### APIs

```text
GET /api/audit-logs
GET /api/audit-logs/timeline?auditable_type={type}&auditable_id={id}
```

### Global Filters

```text
user_id
event_type
action
auditable_type
auditable_id
date_from
date_to
search
per_page
```

### Frontend Notes

- Use timeline endpoint for entity detail pages.
- Use global logs for admin/security/compliance page.
- Do not show sensitive fields if they somehow exist.

---

## 18. Attachments Module

### Screens

- Generic attachment component
- Upload attachment modal
- Attachment list
- Download attachment
- Delete attachment

### Supported Entities

```text
merchants
orders
shipments
expenses
merchant_invoices
warehouse_returns
treasury_transactions
driver_manifests
```

### APIs

```text
POST /api/attachments
GET /api/attachments
GET /api/attachments/{attachment}
GET /api/attachments/{attachment}/download
DELETE /api/attachments/{attachment}
```

### Frontend Notes

- Use multipart/form-data.
- Max 10MB.
- Allowed: pdf, jpg, jpeg, png, webp, csv, xlsx.
- Build as reusable component:

```text
<Attachments attachableType="expenses" attachableId={id} />
```

---

## 19. Export System

### Screens

- Export history
- Generate export modal
- Download export

### Supported Export Types

```text
orders
shipments
driver_manifests
merchant_wallet_transactions
finance_reports
warehouse_inventory
treasury_transactions
audit_logs
```

### APIs

```text
POST /api/exports/{type}
GET /api/exports
GET /api/exports/{exportLog}
GET /api/exports/{exportLog}/download
```

### Frontend Notes

- Current format: CSV.
- XLSX/PDF can be placeholders later.
- Show export logs with generated_by, generated_at, status, download URL.

---

## 20. Recommended Admin Sidebar

```text
Dashboard
Users & Roles
  Users
  Roles
  Permissions
Merchants
  Merchants
  Integration Tokens
Locations & Pricing
  Governorates
  Areas
  Shipping Rates
  Pricing Plans
Orders
Shipments
Drivers
  Drivers
  Driver Manifests
Warehouse
  Warehouses
  Products
  Inventory
  Stock Movements
  Locations
  Picking Lists
  Packing Tasks
  Returns
  Charges
  Storage Fees
  Pick & Pack Fees
Finance
  Overview
  Wallets
  Payouts
  Expenses
  Invoices
  Treasury
  Reports
Operations
  Barcode Lookup
  Exports
  Attachments
System
  Settings
  Audit Logs
```

---

## 21. Reusable Frontend Components Needed

```text
DataTable with filters
PermissionGate
StatusBadge
MoneyDisplay
DateRangeFilter
MerchantSelector
DriverSelector
GovernorateAreaSelector
Timeline
Attachments
ExportButton
BarcodeScannerInput
ConfirmActionModal
ApprovalActionButtons
PrintView
```

---

## 22. Admin Backend Completion Status

### Completed for Admin Frontend

```text
Auth
Users
Roles & Permissions
Settings
Audit Logs
Merchants
Pricing Plans
Locations
Orders
Shipments
Drivers
Driver Manifests
Warehouse
Barcode
Finance Ledger / Wallet
Treasury
Reports
Exports
Attachments
Merchant Integration Tokens foundation
```

### Not Required Before Admin Frontend

```text
Merchant Portal login
WooCommerce plugin
Shopify OAuth
WhatsApp/SMS provider integration
Mobile driver app
Live GPS tracking
```

---

## 23. Best Way to Present API Documentation to Frontend Team

Use two documents, not one giant file only:

### 1. Frontend Flow Documentation

This file. It tells the frontend team:

```text
what screens to build
what actions exist
what flows are expected
which APIs are used per screen
what permissions matter
```

### 2. Raw API Reference

Keep the current `api-documentation.md` as the detailed endpoint reference with examples.

Recommended docs structure:

```text
/docs
  api-documentation.md          raw endpoint reference
  admin-frontend-flows.md       frontend implementation guide
  testing.md                    backend test instructions
```

Optional later:

```text
openapi.yaml / swagger.json
Postman collection
```

---

## 24. Frontend Build Order Recommendation

### Sprint 1

```text
Auth
Layout
Dashboard
Users
Roles & Permissions
Settings
```

### Sprint 2

```text
Merchants
Locations
Pricing Plans
Orders
```

### Sprint 3

```text
Shipments
Drivers
Driver Manifests
Barcode Lookup
```

### Sprint 4

```text
Warehouse
Picking
Packing
Returns
Warehouse Billing
```

### Sprint 5

```text
Finance
Wallet
Payouts
Expenses
Invoices
Treasury
Reports
```

### Sprint 6

```text
Audit Logs
Exports
Attachments
Integration Tokens
Final polish
```

---

## 25. Important Integration Notes for Frontend

- Always handle `401`, `403`, `422`, and `404` globally.
- Use backend `permissions` to control sidebar and action visibility.
- Use backend filters; do not filter large lists only on frontend.
- Keep order/shipment status transitions controlled by backend responses.
- Do not calculate financial balances on frontend; display backend values.
- Do not expose integration token after creation.
- Activity timeline should be reusable and lazy-loaded.
- Attachments should be reusable across entity detail screens.
- Exports should be asynchronous-ready even if current implementation is immediate.

---

## 26. Admin Catalog Screen

### Screen

`/admin/company-catalog`

### Purpose

- Manage company-owned packaging products and service catalog items.
- This is not merchant inventory and should not reuse warehouse product screens.

### Main UI

- Tabs or filter chips by type:
  - Packaging Products
  - Storage Services
  - Fulfillment Services
  - Other Services
- Table columns:
  - Name
  - SKU
  - Type
  - Category
  - Unit Price
  - Merchant Visible
  - Status
  - Sort Order

### APIs

```text
GET /api/company-catalog-items
POST /api/company-catalog-items
GET /api/company-catalog-items/{companyCatalogItem}
PUT /api/company-catalog-items/{companyCatalogItem}
DELETE /api/company-catalog-items/{companyCatalogItem}
```

### Permissions

- `company_catalog.view`
- `company_catalog.create`
- `company_catalog.update`
- `company_catalog.delete`

---

## 27. Order Packaging Selection Flow

### Screen Impact

- Admin order create
- Admin order edit
- Future merchant portal order create/edit

### UI Behavior

1. Keep the current `requires_packaging` and `package_notes` fields.
2. If packaging is enabled, show one or more packaging lines.
3. Each line should allow:
   - packaging product dropdown
   - quantity
   - optional notes
4. Show backend-calculated `total_packaging_fee`.

### Data Rules

- Only active catalog items with `type = packaging_product` should be selectable.
- Do not mix packaging product dropdowns with warehouse product selectors.
- If packaging is turned off, clear packaging lines before submit.

### Order Payload Additions

```text
requires_packaging
package_notes
packaging_items[]
```

### Order Response Additions

```text
total_packaging_fee
packaging_items[]
```

---

## 28. Merchant Shop Preview Screen

### Screen

Inside merchant details or as a dedicated preview page:

`/admin/merchants/{merchantId}/shop-preview`

### Purpose

- Let admin verify exactly what the merchant-facing shop will show.

### Sections

- Packaging products
- Storage fee rules
- Pick & pack fee rules
- Visible services

### APIs

```text
GET /api/merchant-shop/catalog
GET /api/merchant-shop/pricing
GET /api/merchants/{merchant}/shop-preview
```

### Important Notes

- Read-only display only.
- No cart.
- No checkout.
- No purchase flow.
- Respect merchant shop settings when a section is disabled.

---

## 29. Merchant Store Future Page

### Future Merchant Portal Placement

`/merchant/store`

### Expected Behavior

- Show merchant-visible packaging products and service catalog entries.
- Show merchant pricing references like storage and pick-pack rules.
- Keep this page informational only in the current backend phase.

### Do Not Build Yet

- cart
- checkout
- payment flow
- catalog purchase flow

---

## 30. Merchant Registration Review

### End-to-end flow

```text
Merchant Sign Up
      ↓
Pending / Account Under Review
      ↓
Super Admin Review
      ├─ Approve → Active → Merchant Dashboard
      └─ Reject  → Rejected → Rejection Status Screen
```

### Admin navigation and routes

Add `Merchants > Registration Requests`, visible with `merchant_registration.view`.

- List route: `/admin/merchants/registration-requests`
- Review route: `/admin/merchants/registration-requests/:merchantId`
- Pending badge source: `data.pending_merchant_registrations` from `GET /api/dashboard/summary`

The list should default to Pending and offer Pending, Approved (`active`), Rejected, and All filters. Columns: Company, Owner, Email, Phone, Registration Date, Status, and Review action.

The review screen shows company/contact/location information, the primary owner login and account status, submitted date/source/current status, and historical decision metadata.

### Decision UI

Approve opens a confirmation modal with optional pricing plan and notes, then calls `POST /api/merchant-registration-requests/{merchant}/approve`. On success, show a toast, update details immediately, and remove the record from the pending view.

Reject opens a modal with a mandatory reason (maximum 1000 characters), then calls `POST /api/merchant-registration-requests/{merchant}/reject`. Keep the action disabled until a non-empty reason is present.

Do not implement a generic status selector for registration decisions. Hide/disable decision actions after a request leaves `pending`, and handle HTTP `409` by refreshing the record because another admin may have reviewed it.

### Merchant-facing routes required in the frontend application

This repository contains the API and Blade print views, not the Merchant/Admin SPA. The frontend application should add:

- `/register`: company and primary owner form, including password confirmation and optional location selectors.
- `/registration-submitted`: clarify that the account exists but is not operational yet; offer Sign In.
- `/merchant/account-under-review`: show company, registration date, Pending Review, profile/settings/notifications, and logout only.
- `/merchant/registration-rejected`: show the admin reason, review date, public support contact when configured, and logout only.

After login, load `/api/merchant-portal/me` before mounting navigation. Render the normal merchant shell only for `active`; never briefly expose operational navigation for `pending`, `rejected`, or `suspended`.

---

## 31. Pre-Printed Waybills

This repository supplies the Admin/Merchant APIs and printable label views; the separate SPA should add the screens below.

### Lifecycle

```text
Generate Pre-Printed Batch
        ↓
Reserve Unique Numbers (single locked sequence range)
        ↓
Persist Available Waybills
        ↓
Print / Reprint Existing Labels
        ↓
Scan During Order Creation
        ↓
Assigned to Order
        ↓
Linked to Shipment (same waybill)
```

Reprinting never creates or reserves numbers. A void waybill stays void permanently.

### Navigation and list page

Add `Shipments > Pre-Printed Waybills`, guarded by `waybills.view`, at:

`/admin/shipments/preprinted-waybills`

Load `GET /api/waybill-batches`. Render KPI cards from top-level `summary`: Total Generated, Available, Assigned, and Void. The table uses Batch Number, Quantity, Available, Assigned, Void, Generated By, Generated At, and actions. Support date/generator/search filters and server pagination. Show View to `waybills.view` users and Print/Reprint only with `waybills.print`.

### Generate modal

The primary Generate Waybills action is visible only with `waybills.generate`. Fields:

- Number of Waybills (required, minimum 1; display the configured maximum)
- Label Template (default `default`)
- Notes (optional)

Submit `POST /api/waybill-batches`. On `201`, show the exact quantity and returned `first_waybill_number → last_waybill_number`, then offer Print Now and View Batch. Keep the button disabled while submitting; server-side sequence locking and unique constraints remain authoritative if duplicate browser requests occur.

### Batch details

Route: `/admin/shipments/preprinted-waybills/:batchId`

Load `GET /api/waybill-batches/{batch}`. Show the batch header/counts and a table with Waybill, Status, Order, Shipment, Generated, and contextual actions:

- available: Print and, with `waybills.void`, Void;
- assigned: View Order/Shipment and Print;
- void: display the stored reason and allow read-only inspection.

Batch Print/Reprint calls `GET /api/waybill-batches/{batch}/print` with the selected existing label format. Individual Print calls `GET /api/waybills/{waybill}/label`. Void requires a non-empty reason and posts it to `/api/waybills/{waybill}/void`.

For physical pre-printed stock that must contain only the Waybill Number and scannable barcode, use `GET /api/waybill-batches/{batch}/simple-labels/print`. Individual minimal reprints use `GET /api/waybills/{waybill}/simple-label`. Both require `waybills.print`, support the existing `json`, `html`, `pdf`, `zpl`, and `tspl` formats, and never reserve or assign another number.

### Admin and Merchant create-order forms

Add a Waybill section to both forms:

```text
(●) Generate automatically
( ) Use pre-printed waybill
```

Automatic is the default and sends no extra value (or `waybill_mode: automatic`). Pre-printed reveals a scanner/text input and sends:

```json
{
  "waybill_mode": "preprinted",
  "preprinted_waybill_number": "WB-001027"
}
```

Optionally call `POST /api/waybills/validate` after scanning to provide early feedback. A successful validation does not reserve the label; only successful order creation assigns it. Handle `WAYBILL_ALREADY_ASSIGNED` as a race/conflict and ask the user to scan another label. Also surface `WAYBILL_NOT_FOUND`, `WAYBILL_VOID`, and `INVALID_WAYBILL_SOURCE` without discarding the rest of the form.

Merchant navigation must not expose batch generation, batch history, void, sequence, or prefix controls. Merchant users only choose/scan a globally available physical pre-printed label while creating their own merchant's order.
