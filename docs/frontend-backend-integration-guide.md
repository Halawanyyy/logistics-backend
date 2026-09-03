# Frontend–Backend Integration Guide

> **Source of truth:** the registered Laravel 12 routes, middleware, FormRequests, API Resources, controllers, actions/services, enums, migrations, defaults, and feature tests in this repository as inspected on 2026-08-13. This is a frontend implementation contract, not an endpoint dump. Existing API documents may be older than the code; where they disagree, this guide follows the code.

## Table of contents

1. [System overview](#1-system-overview)
2. [Application areas and base URLs](#2-application-areas-and-base-urls)
3. [Backend conventions](#3-backend-conventions)
4. [Authentication](#4-authentication)
5. [Authorization and identities](#5-authorization-and-identities)
6. [Global API behavior](#6-global-api-behavior)
7. [Admin navigation map](#7-admin-navigation-map)
8. [Merchant navigation map](#8-merchant-navigation-map)
9. [Dashboard](#9-dashboard)
10. [Users](#10-users)
11. [Roles and permissions](#11-roles-and-permissions)
12. [Complete permission matrix](#12-complete-permission-matrix)
13. [Merchants](#13-merchants)
14. [Merchant registration and review](#14-merchant-registration-and-review)
15. [Merchant portal users](#15-merchant-portal-users)
16. [Company catalog](#16-company-catalog)
17. [Merchant shop](#17-merchant-shop)
18. [Locations](#18-locations)
19. [Pricing plans and shipping rates](#19-pricing-plans-and-shipping-rates)
20. [Orders](#20-orders)
21. [Order packaging](#21-order-packaging)
22. [Shipments](#22-shipments)
23. [Partial delivery](#23-partial-delivery)
24. [Delivery attempts](#24-delivery-attempts)
25. [Public tracking](#25-public-tracking)
26. [Waybills](#26-waybills)
27. [Simple waybill labels](#27-simple-waybill-labels)
28. [Preprinted waybill order UX](#28-preprinted-waybill-order-ux)
29. [Labels and printing](#29-labels-and-printing)
30. [Drivers](#30-drivers)
31. [Driver manifests and cash closure](#31-driver-manifests-and-cash-closure)
32. [Warehouse foundations](#32-warehouse-foundations)
33. [Warehouse locations](#33-warehouse-locations)
34. [Warehouse products](#34-warehouse-products)
35. [Inventory](#35-inventory)
36. [Warehouse receipts](#36-warehouse-receipts)
37. [Warehouse transfers](#37-warehouse-transfers)
38. [Warehouse returns](#38-warehouse-returns)
39. [Picking](#39-picking)
40. [Packing](#40-packing)
41. [Storage and pick-pack fees](#41-storage-and-pick-pack-fees)
42. [Finance architecture](#42-finance-architecture)
43. [Merchant wallet](#43-merchant-wallet)
44. [System-generated finance events](#44-system-generated-finance-events)
45. [Payouts](#45-payouts)
46. [Expenses](#46-expenses)
47. [Merchant invoices](#47-merchant-invoices)
48. [Treasury](#48-treasury)
49. [Finance reports and reconciliation](#49-finance-reports-and-reconciliation)
50. [Merchant integration tokens](#50-merchant-integration-tokens)
51. [External integration API](#51-external-integration-api)
52. [Attachments](#52-attachments)
53. [Exports](#53-exports)
54. [Merchant notifications](#54-merchant-notifications)
55. [Settings](#55-settings)
56. [Audit logs](#56-audit-logs)
57. [Barcode scanning](#57-barcode-scanning)
58. [Pagination and filters](#58-pagination-and-filters)
59. [Error and business-code contract](#59-error-and-business-code-contract)
60. [TypeScript contracts](#60-typescript-contracts)
61. [API client and data fetching](#61-api-client-and-data-fetching)
62. [Status badges and display conventions](#62-status-badges-and-display-conventions)
63. [Recommended frontend routes](#63-recommended-frontend-routes)
64. [Admin versus merchant API matrix](#64-admin-versus-merchant-api-matrix)
65. [Read, mutation, and destructive actions](#65-read-mutation-and-destructive-actions)
66. [Download, print, and upload contract](#66-download-print-and-upload-contract)
67. [Form field reference](#67-form-field-reference)
68. [Screen implementation checklist](#68-screen-implementation-checklist)
69. [Dependencies and implementation order](#69-dependencies-and-implementation-order)
70. [Backend questions, gaps, and traceability](#70-backend-questions-gaps-and-traceability)

## 1. System overview

This backend runs a logistics, last-mile shipping, warehouse-fulfilment, and merchant-finance platform. Internal operators manage merchants, orders, shipments, drivers, inventory, billing, and system configuration. Merchant members manage only their own company through a separate portal. Public users can register a merchant, read public settings, and track a shipment. External commerce systems can create orders through scoped integration tokens.

All **22 module directories** are covered: Attachment, Audit, Auth, Barcode, CompanyCatalog, Dashboard, Driver, Export, Finance, Label, LocationPricing, Merchant, MerchantIntegration, MerchantPortal, MerchantRegistration, Order, RolePermission, Settings, Shipment, UserManagement, Warehouse, and Waybill.

The identities are deliberately separate:

- A `User` is an authentication account. Internal users receive roles and permissions. A user whose role is `merchant_portal` instead has a merchant membership.
- A `Merchant` is the commercial company/ledger owner, not a login account.
- A merchant portal membership joins one user to one merchant and supplies a portal role, status, and capabilities. The middleware requires exactly one valid membership.
- A `Driver` is an operational record and may optionally reference a user. There is no dedicated driver login or driver portal API. Creating an internal user with role `driver` does not create a `Driver` record automatically.
- Super administrators receive all seeded permissions. Other internal roles are permission-based; names alone are not an authorization contract.

The principal identifiers are different: `ORD-…` is an order number, `SHP-…` is a shipment number, and `WB-…` is a waybill number. Never substitute one for another in the UI.

## 2. Application areas and base URLs

| Area | Prefix | Authentication | Scope |
|---|---|---|---|
| Internal Admin/Super Admin | `/api/*` | Sanctum bearer token plus route permission | Cross-merchant/internal operations |
| Merchant Portal | `/api/merchant-portal/*` | Sanctum bearer token, merchant membership, merchant capability | Current membership's merchant only |
| Authentication | `/api/auth/*` | Login is public; `me`, `logout`, `ping` require token | Current user |
| Public | `/api/public/*`, `/api/track/*` | None | Public settings and shipment tracking |
| External Merchant Integration | `/api/integrations/*` | Integration bearer token or `X-Merchant-Token` | Token's merchant only |

The code registers **384 API routes**: 303 internal/general, 73 merchant-portal, 4 auth, 2 external integration, 1 public-settings, and 1 public-tracking route. All are represented in the feature contracts and endpoint index in this document.

## 3. Backend conventions

- JSON requests use `Content-Type: application/json`; attachments use `multipart/form-data`.
- IDs are numeric. Dates use `YYYY-MM-DD`; timestamps are serialized by Laravel resources and should be treated as ISO-compatible strings, never parsed as server-local text.
- Money resources commonly serialize decimal casts as strings. Preserve two-decimal precision; do not use binary floating point for calculations.
- Resource responses are generally `{ "data": ... }`. Action endpoints often return `{ "message": "…", "data": ... }`; deletion often returns only `message`.
- Most list endpoints return an unpaginated Resource collection `{ "data": [] }`. Only the six families identified in §58 paginate.
- PATCH/PUT update requests use `sometimes`; send only changed fields. Nullable fields must be explicitly sent as `null` when clearing them.
- Soft-deleted records fail route model binding/`exists` rules where the request constrains `deleted_at`.
- English and Arabic locale selection is accepted through `X-Locale: en|ar` or `Accept-Language`; backend enum values remain English machine strings.
- CORS permits configured origins and local development at ports 3000/5173, all methods/headers, with credentials disabled by default. Use bearer tokens, not cookie assumptions.

## 4. Authentication

### Login screen

`POST /api/auth/login` accepts `email` (required, valid email) and `password` (required string). A successful response is:

```json
{
  "message": "Logged in successfully",
  "token": "plain-sanctum-token",
  "user": { "id": 1, "name": "…", "email": "…", "role": "…" }
}
```

Invalid credentials return `401 {"message":"Invalid credentials"}`. Login is limited to 10 attempts/minute per IP plus normalized email. Store the token securely and send `Authorization: Bearer {token}`. The backend does not issue a refresh token.

`GET /api/auth/me` returns `{ "user": <raw user> }`; `POST /api/auth/logout` deletes only the current token; `GET /api/auth/ping` verifies authenticated reachability. The legacy authenticated `GET /api/user` also returns the raw current user; prefer `/api/auth/me` for one consistent bootstrap. On logout, clear token, user, permissions, membership, and cached private queries.

Recommended bootstrap:

```text
login → store token → GET /api/auth/me
      → if role=merchant_portal call GET /api/merchant-portal/me
      → otherwise load the user/role permissions
      → render the correct shell and permitted navigation
```

An invalid/expired/revoked token returns 401: clear authentication and redirect to the appropriate login. Do not log out for ordinary 403 responses. The login controller does **not** check an internal-user active/suspended flag (there is no such user field); merchant operational state is enforced after login by merchant middleware.

## 5. Authorization and identities

Internal routes use `auth:sanctum` plus permission middleware. The response user/role resources should drive navigation, but hiding UI is only UX; the backend is security authority.

Merchant routes use three layers: token auth, `EnsureMerchantPortalUser`, and `EnsureMerchantOperational`. Requests may not inject `merchant_id` or protected internal fields; attempts return 422. Normal endpoints require an active membership and active merchant. Account-safe endpoints—`me`, profile, settings, and notifications—remain available in restricted merchant states as allowed by middleware.

Merchant capabilities by portal role:

| Role | Capabilities |
|---|---|
| `owner` | All portal capabilities, including member management |
| `admin` | All owner capabilities except `users.view`/`users.manage` |
| `staff` | Profile/dashboard; order create/update/confirm/cancel; shipments; warehouse and inbound work; labels; attachment upload; notifications; shop |
| `accountant` | Read profile/dashboard/orders/shipments/warehouse/inbound; all finance/wallet/invoice/payout and export actions; attachment read/download; notifications/shop |
| `warehouse_viewer` | Read profile/dashboard/warehouse/inbound/shipments; label printing; exports; attachment read/download; notifications/shop |
| `viewer` | Read dashboard/profile/orders/shipments/warehouse/finance/wallet/invoices/notifications/shop |

Capability strings are: `profile.view`, `profile.update`, `settings.view`, `settings.update`, `dashboard.view`, `users.view`, `users.manage`, `orders.view`, `orders.create`, `orders.update`, `orders.confirm`, `orders.cancel`, `shipments.view`, `warehouse.view`, `warehouse.inbound_requests.view`, `warehouse.inbound_requests.create`, `warehouse.inbound_requests.cancel`, `finance.view`, `wallet.view`, `invoices.view`, `invoices.download`, `payouts.view`, `payouts.request`, `labels.print`, `integrations.view`, `integrations.manage`, `exports.view`, `exports.create`, `exports.download`, `attachments.view`, `attachments.upload`, `attachments.download`, `attachments.delete`, `notifications.view`, and `store.view`.

## 6. Global API behavior

Every page needs four explicit states: initial skeleton, loaded data, empty result with a filter-reset/create affordance, and recoverable error with retry. Mutations need an in-flight disabled state to prevent double submission. Refresh the detail and affected list after success; do not optimistically invent state transitions that the service may reject.

`422` validation shape:

```json
{
  "message": "The given data was invalid.",
  "errors": { "items.0.quantity": ["The items.0.quantity field …"] }
}
```

Map dotted/nested keys back to row fields. A business response can also include `code`; use that stable code before matching message text. See §59.

## 7. Admin navigation map

| Group | Item | Minimum permission |
|---|---|---|
| Overview | Dashboard | `dashboard.view` |
| Operations | Orders; Shipments; Delivery Attempts; Drivers; Manifests | respective `orders.view`, `shipments.view`, `delivery_attempts.view`, `drivers.view`, `driver_manifests.view` |
| Merchants | Merchants; Registration; Portal Users; Catalog; Shop preview; Integrations | `merchants.view`, `merchant_registration.view`, `merchant_portal_users.manage`, `company_catalog.view`, `merchant_shop.view`, `merchant_integrations.view` |
| Pricing | Governorates; Areas; Pricing Plans; Rates | `locations.view`, `pricing_plans.view`, `shipping_rates.view` |
| Warehouse | Warehouses; Locations; Products; Inventory; Receipts; Transfers; Returns; Picking; Packing; Charges; Fee Rules | matching warehouse permissions in §12 |
| Waybills | Batches; Waybills; Labels | `waybills.view`, `labels.view` |
| Finance | Wallet; Payouts; Expenses; Invoices; Treasury; Cash Closures; Reports | matching finance permissions |
| System | Users; Roles; Permissions; Settings; Audit; Attachments; Exports; Barcode | matching system permissions |

Render a parent group if at least one child is visible. Tracking may be a public utility screen and does not need an internal menu permission.

## 8. Merchant navigation map

| Route | Item | Capability |
|---|---|---|
| `/merchant/dashboard` | Dashboard | `dashboard.view` |
| `/merchant/orders` | Orders | `orders.view` |
| `/merchant/shipments` | Shipments | `shipments.view` |
| `/merchant/warehouse/*` | Products, inventory, inbound requests, charges | `warehouse.view` / inbound capabilities |
| `/merchant/finance` | Summary and wallet | `finance.view` / `wallet.view` |
| `/merchant/invoices` | Invoices | `invoices.view` |
| `/merchant/payouts` | Payouts | `payouts.view` |
| `/merchant/integrations` | Tokens/docs/test | `integrations.view` |
| `/merchant/exports` | Exports | `exports.view` |
| `/merchant/shop` | Read-only shop (currently blocked: no scoped API route) | `store.view` |
| `/merchant/notifications` | Notifications | `notifications.view` |
| `/merchant/team` | Members | `users.view` |
| `/merchant/profile`, `/merchant/settings` | Account | matching profile/settings capability |

After `/merchant-portal/me`, route by merchant status: `pending` → approval-waiting page, `rejected` → reason page, `suspended` → suspended page, `active` → normal portal. Preserve account-safe navigation. Unknown/inactive states get a generic unavailable page.

## 9. Dashboard

Internal `GET /api/dashboard/summary` (`dashboard.view`) supplies the admin overview. Merchant `GET /api/merchant-portal/dashboard/summary` (`dashboard.view`) is merchant-scoped. Build separate query keys; the schemas and scope are not interchangeable. Use cards only for returned counters/totals and show zero, not blank, for numeric empty data. Refresh after order, shipment, inventory, or finance mutations that can change totals.

## 10. Users

Admin screens: list, create, detail/edit, and role assignment. Endpoints are `GET/POST /api/users`, `GET/PUT/DELETE /api/users/{user}`, and `POST /api/users/{user}/assign-role`. Permissions are `users.view/create/update/delete`; assignment needs `roles.assign`.

The create/edit forms expose the fields validated by the current request: name, email (unique), password with confirmation where required, and role/role assignment as supported. Never expose Sanctum tokens or password hashes. List responses are unpaginated. After assignment, invalidate user detail/list and current-session permission data if the edited user is the signed-in user.

## 11. Roles and permissions

`GET/POST /api/roles`, `GET/PUT/DELETE /api/roles/{role}`, and `POST /api/roles/{role}/sync-permissions` implement role management. `GET /api/permissions` lists permissions and `GET /api/permissions/grouped` returns module groupings. Permissions: `roles.view/create/update/delete`, `permissions.view`, `permissions.assign`.

The role editor should load grouped permissions, send exact backend slugs, and display an indeterminate module checkbox. Never allow the frontend to manufacture permission names. Seeded role intent: `super_admin` all; `admin` broad core access; `staff` operations; `warehouse` warehouse operations; `finance` finance; `driver` limited dashboard/driver/manifest/shipment/barcode; `merchant_portal` uses capabilities, not internal permissions.

## 12. Complete permission matrix

The defaults define **170 permissions in 20 groups**. Every value below is covered. Compact slash notation repeats the prefix (for example, `users.view/create/update/delete` means the four exact slugs `users.view`, `users.create`, `users.update`, `users.delete`).

| Module | Permission slugs | UI use |
|---|---|---|
| Attachments (4) | `attachments.view`, `.upload`, `.download`, `.delete` | Attachment panel/actions |
| Audit (2) | `audit_logs.view`, `.timeline` | Audit list/timeline |
| Barcodes (2) | `barcodes.lookup`, `.scan` | Lookup/scanner actions |
| Company Catalog (5) | `company_catalog.view`, `.create`, `.update`, `.delete`; `merchant_shop.view` | Catalog and preview |
| Dashboard (2) | `dashboard.view`, `.merchant_view` | Internal/merchant drill-down dashboards |
| Drivers (12) | `drivers.view/create/update/delete/manifest/cash_closure`; `driver_manifests.view/create/close/print`; `delivery_attempts.view/create` | Driver operations |
| Exports (3) | `exports.view`, `.create`, `.download` | Export center |
| Finance (32) | `finance.view`; `merchant_wallet.view/deposit/withdraw/approve/reject`; `finance.payouts.view/create/update/approve/reject/complete`; `finance.expenses.view/create/update/approve/reject/pay`; `finance.invoices.view/create/download`; `finance.reports.view`; `finance.treasury.view/create/update/delete`; `finance.reports.daily_cash/merchant_ledger/driver_discrepancies/warehouse_revenue/profit_loss`; `finance.reconciliation.view` | Finance pages and actions |
| Labels (3) | `labels.view`, `.preview`, `.print` | Settings, preview, printing |
| Locations/Pricing (16) | `locations.view/create/update/delete`; `shipping_rates.view/create/update/delete`; `pricing_plans.view/create/update/delete`; `pricing_plan_rates.view/create/update/delete` | Location and rate CRUD |
| Merchant Integrations (4) | `merchant_integrations.view/create/revoke/delete` | Token administration |
| Merchant Registration (3) | `merchant_registration.view/approve/reject` | Registration review |
| Merchants (7) | `merchants.view/create/update/delete`; `merchant_portal_users.manage`; `merchant_inbound_requests.view/manage` | Merchant administration |
| Orders (8) | `orders.view/create/update/delete/confirm/create_shipment`; `order_packaging.view/manage` | Order and packaging actions |
| Roles/Permissions (6) | `roles.view/create/update/delete`; `permissions.view/assign` | Access control |
| Settings (5) | `settings.view/update/public_view/groups/bulk_update` | Settings UI/public branding |
| Shipments (6) | `shipments.view/create/update_status/assign_driver/print/return_to_warehouse` | Shipment operations |
| User Management (4) | `users.view/create/update/delete` | Internal accounts |
| Warehouse (42) | `warehouses.view/create/update/delete`; `warehouse_products.view/create/update/delete`; `inventory.view/adjust/reserve/release/fulfill`; `warehouse_returns.view/create`; `warehouse_charges.view/create/update/delete`; `warehouse_locations.view/create/update/delete`; `picking_lists.view/create/update/cancel/scan`; `packing_tasks.view/create/complete`; `storage_fee_rules.view/create/update/delete`; `storage_fees.generate`; `pick_pack_fee_rules.view/create/update/delete`; `pick_pack_fees.generate`; `warehouse_kpis.view` | Warehouse screens/actions |
| Waybills (4) | `waybills.view/generate/print/void` | Batch, validate, print, void |

Route middleware currently uses 150 distinct slugs; the remaining defaults are still part of the intended UI permission vocabulary. Always honor a 403 even when a seeded role normally has access.

## 13. Merchants

Admin screens use `GET/POST /api/merchants`, `GET/PUT/DELETE /api/merchants/{merchant}` plus detail tabs: dashboard, finance, integration tokens, portal users, shop preview, warehouse charges/inventory/movements, and wallet endpoints. Merchant status values are `pending`, `active`, `rejected`, `suspended`, `inactive`.

Core form fields are company/name, owner/contact fields, email, phone, address, governorate/area (area must belong to governorate), pricing plan, status, and notes as exposed by the Store/Update requests. Emails and identifying fields obey unique/exists validation. The response resource is authoritative for table columns; use returned nested governorate, area, pricing plan, and reviewer data rather than fabricating names.

Do not use generic merchant update to review a pending/rejected registration. The backend responds with `USE_MERCHANT_REGISTRATION_REVIEW` or `INVALID_MERCHANT_REGISTRATION_TRANSITION`; use the dedicated review endpoints. Destructive delete requires `merchants.delete`, a confirmation dialog, and post-success invalidation of merchant-derived lists.

## 14. Merchant registration and review

Public sign-up is `POST /api/merchant-portal/register`, limited to 5/hour. Fields: `company_name` required string; `owner_name` required string; `email` required valid and unique across relevant user/merchant records; `phone` required; `password` required minimum 8 and `password_confirmation`; optional `address`, `governorate_id`, and `area_id` with area/governorate consistency. Internal status/reviewer fields and `merchant_id` are prohibited.

Success creates a pending merchant, a merchant-portal user, and an active `owner` membership and returns `message` plus `data` containing `merchant_id`, company, `status`, and `registered_at`. The owner may authenticate immediately but normal business APIs return `403` with `MERCHANT_PENDING_APPROVAL`.

```text
sign up → pending merchant + owner → login → account-under-review
                                      ↓ admin review
                         approve + optional pricing plan → active portal
                         reject + required reason         → rejection page
```

Admin review screens:

| Screen/action | API | Permission | Contract |
|---|---|---|---|
| Requests list | `GET /api/merchant-registration-requests` | `merchant_registration.view` | Filters status/search/company/owner email/phone/date range; paginated, default 25, max 100 |
| Request detail | `GET /api/merchant-registration-requests/{merchant}` | same | Merchant, owner membership, review metadata |
| Approve | `POST …/{merchant}/approve` | `.approve` | Optional `pricing_plan_id`, optional notes; only pending; self-review forbidden |
| Reject | `POST …/{merchant}/reject` | `.reject` | Required rejection reason up to 1000 chars; only pending; self-review forbidden |

Conflicts use `INVALID_MERCHANT_REGISTRATION_TRANSITION` (409); reviewing one's own registration uses `MERCHANT_SELF_REVIEW_FORBIDDEN` (403). A rejected merchant sees the returned rejection reason. A suspended merchant gets `MERCHANT_SUSPENDED`; another unavailable state gets `MERCHANT_ACCESS_UNAVAILABLE`. Do not repeatedly retry these responses.

## 15. Merchant portal users

Admin manages a merchant's members through `/api/merchants/{merchant}/portal-users` (list/create) and `/api/merchant-portal-users/{user}` (show/update/delete/activate/suspend), gated by `merchant_portal_users.manage`. Merchant owners use the scoped equivalents `/api/merchant-portal/users` and `/api/merchant-portal/users/{user}` with activate/suspend actions and `users.view/manage` capabilities.

Expose name/email, portal role (`owner`, `admin`, `staff`, `accountant`, `warehouse_viewer`, `viewer`), membership status (`invited`, `active`, `suspended`), and password fields only where the request permits them. Never show another merchant or allow `merchant_id` in portal payloads. Refresh membership and `/me` after changing the current member. Confirm suspension/deletion because access is removed immediately.

## 16. Company catalog

Admin CRUD: `GET/POST /api/company-catalog-items`, `GET/PUT/DELETE /api/company-catalog-items/{item}` with `company_catalog.*` permissions. Filters include the request-supported type/category/status/visibility/search criteria. List is unpaginated.

| Field | Rule/meaning |
|---|---|
| `type` | required: `packaging_product`, `storage_service`, `fulfillment_service`, `other_service` |
| `category` | nullable string, max 100 |
| `name` | required string, max 255 |
| `sku` | nullable max 255, unique among non-deleted records |
| `description` | nullable text |
| `unit_price` | nullable numeric ≥ 0; `currency` nullable max 10 |
| `size_label` | nullable max 255 |
| `length`, `width`, `height`, `weight` | nullable numeric ≥ 0 |
| `unit` | nullable max 50 |
| `image_path` | nullable path/URL string, max 2048; there is no catalog image upload endpoint |
| `is_visible_to_merchants` | nullable boolean |
| `sort_order` | nullable integer ≥ 0 |
| `status` | `active` or `inactive`, defaults active |

Order packaging accepts only active, non-deleted `packaging_product` entries. Historical order rows snapshot the catalog details and price.

## 17. Merchant shop

The implemented shop is read-only. Internal users with `merchant_shop.view` can call `GET /api/merchant-shop/catalog`, `GET /api/merchant-shop/pricing`, and `GET /api/merchants/{merchant}/shop-preview`. These display visible active catalog items plus applicable storage and pick-pack pricing. There is no cart, checkout, purchase, or mutation API.

Portal roles advertise `store.view`, and settings can hide storage, pick-pack, and packaging sections, but there is **no registered `/api/merchant-portal/...` shop endpoint**. A standard merchant-portal user has no internal permissions, so the internal merchant-shop endpoints are not a valid portal contract. Keep `/merchant/shop` disabled/feature-flagged until a scoped portal route is added; see §70.

## 18. Locations

Admin governorate CRUD is `GET/POST /api/governorates`, `GET/PUT/DELETE /api/governorates/{governorate}`; area CRUD mirrors it at `/api/areas`. Permissions are `locations.view/create/update/delete`. Merchant portal has read-only `GET /api/merchant-portal/locations/governorates` and `/areas`.

Area forms bind a governorate and enforce relationship consistency wherever an address is submitted. Load governorates first, reset a selected area when governorate changes, then refetch/filter areas. Location lists are unpaginated; cache them but invalidate after admin edits.

## 19. Pricing plans and shipping rates

Pricing plan CRUD is `/api/pricing-plans`; nested plan-rate CRUD is `/api/pricing-plan-rates`. Shipping rate CRUD is `/api/shipping-rates`, with action routes for calculation, force-delete, and restore. Each family has matching `view/create/update/delete` permissions.

The order form should calculate a rate from merchant/plan, origin/destination, order type, fulfilment, weight/value, and other request-supported inputs before submission. A missing applicable rate blocks normal admin/portal creation with 422 `Shipping rate not configured`. External integration may instead create a zero-fee `pending_review` order when `integrations.require_review_on_location_missing` is enabled. Rates include delivery pricing and current swap/return fee fields; use the Resource fields and never derive a price from labels.

Screens: pricing-plan list/editor/detail rates tab; shipping-rate list/editor; calculation tester. Lists are unpaginated. Confirm force deletion, and show restore only for records exposed as deleted by the endpoint.

## 20. Orders

Admin endpoints are `GET/POST /api/orders`, `GET/PUT/DELETE /api/orders/{order}`, `POST /api/orders/{order}/confirm`, `POST /api/orders/{order}/shipments`, inventory actions `reserve-stock`, `release-stock`, `fulfill-from-warehouse`, `GET …/stock-reservations`, `GET …/label`, and warehouse actions `start-picking`, `mark-picked`, `start-packing`, `mark-packed`, `ready-for-dispatch`. Merchant equivalents are list/create/detail/update plus `cancel`, `confirm`, and label under `/api/merchant-portal/orders`. Merchant payloads are forcibly scoped.

### Create/edit contract

| Group | API keys | Rules |
|---|---|---|
| Merchant | `merchant_id` | Admin create required, existing non-deleted merchant; prohibited in portal |
| Customer | `customer_name`, `customer_phone`, `customer_alternate_phone` | name and phone required; max 255/50; alternate nullable max 50 |
| Delivery | `delivery_governorate_id`, `delivery_area_id`, `delivery_address`, `delivery_notes` | governorate/address required; area nullable but must belong; notes nullable |
| Pickup | `pickup_governorate_id`, `pickup_area_id`, `pickup_address`, `pickup_notes` | nullable; area requires and belongs to governorate |
| Order | `order_type`, `original_order_id`, `swap_notes`, `fulfillment_type`, `source`, `status`, `external_order_id` | type `delivery|swap|return`; original order only for swap; source/status/external are admin/internal options |
| Payment | `payment_type`, `cod_amount`, `shipping_fee` | payment `cod|prepaid`; amounts nullable numeric ≥ 0; shipping may be resolved by backend |
| Handling | `is_fragile`, `allow_inspection`, `requires_packaging`, `package_notes` | nullable booleans/text |
| Items | `items[]` | required, at least one; `product_name` required; optional `sku`; `quantity` integer ≥1; `unit_price`, `weight` numeric ≥0; notes nullable |
| Packaging | `packaging_items[]` | item `company_catalog_item_id`, quantity ≥1, notes; item must be active packaging product |
| Waybill | `waybill_mode`, `preprinted_waybill_number` | create only; mode `auto|preprinted`; number required only for preprinted and prohibited for auto |
| Notes | `notes` | nullable text |

Fulfilment canonical values are `pickup_from_merchant`, `from_warehouse`, `pickup_from_customer`; aliases `merchant_pickup` and `warehouse_fulfillment` are accepted then normalized. A return must use `pickup_from_customer`; that fulfilment is return-only. Defaults are delivery, COD, manual source, zero COD, setting-driven status, and pickup-from-merchant (return defaults pickup-from-customer).

### Lifecycle and actions

Order statuses: `draft`, `pending_review`, `confirmed`, `shipment_created`, `partially_delivered`, `delivered`, `failed`, `cancelled`. Confirm only an eligible pre-shipment order. Shipment creation requires confirmed status, items, no prior shipment, and completed warehouse fulfilment when `from_warehouse`. Warehouse stock actions drive the warehouse fulfilment states in §62. A merchant cancel action releases reservations. Admin has no dedicated cancel action; an allowed status update through PUT is the only current path.

Editing is blocked after a shipment, a final state, or an active warehouse workflow. Fulfilment cannot change after shipment/reservation/process activity. Use `allowed_actions` from `OrderResource` for button display, then still handle 409/422 races.

`OrderResource` supplies identifiers, merchant/customer/address fields, type/payment/fulfilment/source/status, amounts, handling flags, shipment/waybill, items, packaging snapshots, `total_packaging_fee`, stock/fulfilment summaries, allowed actions, notes, and timestamps. Build list columns only from those returned fields. Lists are unpaginated; supported filters come from `ListOrdersRequest` (search/status/type/merchant/source/fulfilment and date/location filters where present).

Success behavior: creation navigates to detail; confirmation refreshes detail/list/dashboard; shipment creation navigates or links to the shipment; stock actions refresh order, inventory, reservations, movements, picking/packing queues. Failures retain the form and show field errors plus the server business message.

## 21. Order packaging

When `requires_packaging` is checked, show a repeatable selector backed by visible active catalog `packaging_product` records. Submit:

```json
{"requires_packaging":true,"packaging_items":[{"company_catalog_item_id":12,"quantity":2,"notes":null}]}
```

The backend snapshots name, SKU, category, size, unit price, quantity, total, and notes into `OrderPackagingItem`. Later catalog changes do not rewrite history. Display `total_packaging_fee` from the resource; do not recompute it for accounting. Packaging management uses `order_packaging.view/manage`. No packaging selection is accepted from the external integration create-order request at present.

## 22. Shipments

Admin routes: `GET /api/shipments`, `GET /api/shipments/{shipment}`, `PATCH …/status`, `POST …/assign-driver`, `POST …/delivery-attempt`, `POST …/complete-delivery`, `GET …/label`, `GET …/waybill`; plus `POST /api/shipments/labels/bulk` and `GET /api/shipments/print-list`. Merchant routes are read-only detail/list plus label, tracking, and waybill.

Status transitions enforced by the service:

| Current | Allowed next values |
|---|---|
| `pending_pickup` | `driver_assigned`, `picked_up`, `arrived_at_hub`, `sorting`, `ready_for_dispatch`, `in_transit`, `out_for_delivery`, `cancelled` |
| `driver_assigned` | `picked_up`, `arrived_at_hub`, `sorting`, `ready_for_dispatch`, `in_transit`, `out_for_delivery`, `cancelled` |
| `picked_up` | `arrived_at_hub`, `sorting`, `ready_for_dispatch`, `in_transit`, `out_for_delivery`, `cancelled` |
| `arrived_at_hub` | `sorting`, `ready_for_dispatch`, `in_transit`, `out_for_delivery`, `cancelled` |
| `sorting` | `ready_for_dispatch`, `in_transit`, `out_for_delivery`, `cancelled` |
| `ready_for_dispatch` | `out_for_delivery`, `in_transit`, `cancelled` |
| `in_transit` | `out_for_delivery`, `delivery_attempted`, `rescheduled`, `returned_to_hub`, `returned_to_warehouse`, `failed`, `cancelled` |
| `out_for_delivery` | `delivery_attempted`, `rescheduled`, `delivered`, `partially_delivered`, `failed`, `returned`, `returned_to_hub`, `returned_to_warehouse`, `cancelled` |
| `delivery_attempted` | `out_for_delivery`, `rescheduled`, `failed`, `returned`, `returned_to_hub`, `returned_to_warehouse`, `cancelled` |
| `rescheduled` | `out_for_delivery`, `delivery_attempted`, `failed`, `returned`, `returned_to_hub`, `returned_to_warehouse`, `cancelled` |

Final states are immutable. The generic status endpoint cannot set `delivered`, `partially_delivered`, or `failed`; use complete-delivery. Assignment accepts an eligible driver and changes appropriate shipment state. Detail displays returned order, merchant, driver, waybill, item results, attempts, money, and timestamps from `ShipmentResource`.

## 23. Partial delivery

`POST /api/shipments/{shipment}/complete-delivery` requires `shipment_status` (`delivered`, `partially_delivered`, or `failed`), optional notes, and at least one item result:

```json
{
  "shipment_status":"partially_delivered",
  "items":[
    {"order_item_id":1,"warehouse_product_id":9,"result_status":"delivered","quantity":2,"return_condition":null,"notes":null},
    {"order_item_id":2,"result_status":"returned","quantity":1,"return_condition":"sellable"}
  ]
}
```

Item result values: `delivered`, `returned`, `damaged`, `cancelled`; return condition is required for returned items and is `sellable|damaged|disposed`. Quantities cannot exceed remaining order-item quantities. The backend derives the overall result: all delivered → delivered; any delivered plus another outcome → partially delivered; no delivered quantity → failed. The submitted overall status must match. `shipping.allow_partial_delivery` can prohibit partial results.

Delivered COD is calculated from delivered item quantities and item unit prices by the service; use backend totals. Warehouse-fulfilled sellable returns create deferred warehouse return handling; damaged results affect damaged stock. Completion also synchronizes wallet entries. Refresh shipment, order, inventory/returns, wallet, manifest, and dashboards.

## 24. Delivery attempts

Create with `POST /api/shipments/{shipment}/delivery-attempt` (`delivery_attempts.create`). Fields: required `result`; optional `reason`, `notes`, `next_action`, `next_attempt_date`. Reasons: `customer_not_answering`, `customer_refused`, `wrong_address`, `no_cash`, `postponed_by_customer`, `area_closed`, `damaged_package`, `customer_requested_reschedule`. Results: `failed`, `postponed`, `customer_unreachable`, `customer_refused`, `rescheduled`.

The backend increments attempt number and enforces `shipping.max_delivery_attempts`. Failed moves shipment to failed; postponed/rescheduled moves it to rescheduled; other results move it to delivery-attempted. At the limit, show the 422 business message and disable repeat submission after refresh. Admin list is `GET /api/delivery-attempts`; attempts also appear on shipment detail.

## 25. Public tracking

`GET /api/track/{shipment_number}` is unauthenticated and limited to 120/minute. It accepts a shipment number, not an internal ID. `PublicShipmentTrackingResource` intentionally exposes a public subset: shipment/waybill/order references, current status and timeline-safe delivery information, not merchant finance or customer secrets. A 404 should render “shipment not found”; 429 should preserve the entered number and ask the user to wait.

Merchant authenticated tracking is `GET /api/merchant-portal/shipments/{shipment}/tracking` and remains merchant-scoped.

## 26. Waybills

Waybills use one centralized sequence. Sources are `automatic` and `preprinted`; statuses are `available`, `assigned`, `void`. Automatic order shipment creation consumes the next sequence. Batch creation atomically reserves a range, so the next automatic number begins after the batch and ranges never overlap.

Admin APIs:

| Operation | API | Permission/behavior |
|---|---|---|
| Batch list/create | `GET/POST /api/waybill-batches` | `waybills.view/generate`; create `quantity` 1..configured max (500 default), optional label template max 100 and notes max 1000 |
| Batch detail | `GET /api/waybill-batches/{batch}` | Shows range/count/waybills |
| Batch print/reprint | `GET /api/waybill-batches/{batch}/print` and `/print/simple` | `waybills.print`; printing only audits reprint |
| Validate scan | `POST /api/waybills/validate` | body `waybill_number`; `valid` only for unassigned, non-void preprinted waybill |
| Full/simple label | `GET /api/waybills/{waybill}/label`, `/simple-label` | print formats in §29 |
| Void | `POST /api/waybills/{waybill}/void` | `waybills.void`; send reason where accepted; cannot void assigned/already void |

Validation not-found returns 404 with `WAYBILL_NOT_FOUND` and `valid:false`. Atomic assignment can return `INVALID_WAYBILL_SOURCE` (422), `WAYBILL_VOID` (409), `WAYBILL_ALREADY_ASSIGNED` (409), or `WAYBILL_NOT_AVAILABLE` (409). A successful preliminary validation does not reserve the number; the create-order call is final authority.

## 27. Simple waybill labels

`GET /api/waybills/{waybill}/simple-label` contains only the waybill number and Code 128 barcode/human-readable value. JSON response is `{ "data": { "waybill_number": "WB-000123", "barcode": "WB-000123" } }`. Supported render formats are JSON, HTML, PDF, ZPL, and TSPL.

Printing/reprinting never creates a number, assigns a waybill, changes its status, or increments the sequence. Browser HTML can open in a protected preview; PDF uses a blob; ZPL/TSPL is raw `text/plain` to download or hand to an explicitly configured local print bridge.

## 28. Preprinted waybill order UX

Default the create-order radio to “Generate automatically” (`waybill_mode:auto`). For “Use preprinted” send `waybill_mode:preprinted` and display a scanner/text input. Debounce or explicitly submit `POST /api/waybills/validate`; display returned batch/status/source and enable order submit only if `valid:true`.

Validation is advisory. On submit, handle an assignment race: already assigned/void/unavailable is a recoverable conflict; keep the rest of the order form, clear only the waybill field, and ask for another label. Do not silently fall back to automatic generation.

## 29. Labels and printing

Admin label support includes order, shipment, warehouse product, warehouse bin, driver manifest, and waybill. Routes include `/api/labels/settings`, `/templates`, `/preview`, `/test`; order/shipment/product/bin/manifest/waybill resource label routes; and order/shipment/product bulk routes.

Common render inputs: `format` = `html|pdf|zpl|tspl|json`, optional width 20–210 mm, height 20–297 mm, and `download` boolean where accepted. Defaults come from label settings. Content handling:

| Format | Content type/handling |
|---|---|
| JSON | Parse JSON preview/data |
| HTML | `text/html`; open a protected new window/iframe using fetched content rather than navigating without an auth header |
| PDF | `application/pdf`; request `responseType:'blob'`, create object URL, preview/download, then revoke URL |
| ZPL/TSPL | `text/plain`; download or send to a local printer integration; browsers do not print raw command languages directly |

Bulk shipment/order/product labels accept the relevant ID arrays per request validation. Driver manifest rendering supports HTML/PDF/JSON; asking for raw printer formats currently falls through to JSON (documented as a gap in §70).

## 30. Drivers

CRUD routes are `GET/POST /api/drivers`, `GET/PUT/DELETE /api/drivers/{driver}`; related screens use `GET …/{driver}/shipments`, `/manifest`, and `/cash-expected`. Fields are optional `user_id`, required name/phone, optional national ID, vehicle type/plate, status (`active|inactive`), and notes. Use only active drivers for new assignments.

The driver detail screen should show profile, current shipments/manifest, expected COD, and cash closures. Driver records are not credentials; linking `user_id` is optional. Confirm deletion and handle relationship conflicts from the backend.

## 31. Driver manifests and cash closure

Manifest APIs: list, generate, detail, close, print under `/api/driver-manifests`; status values `open|closed|cancelled`. Generate fields: `driver_id`, manifest date (`YYYY-MM-DD`), at least one `shipment_id`, optional notes. Shipments must be ready-for-dispatch/out-for-delivery, cannot belong to another open manifest, and are assigned to the driver and moved to out-for-delivery.

Close only after every manifest shipment is in an allowed outcome: delivered, partially delivered, failed, delivery-attempted, rescheduled, returned/returned-to-hub/returned-to-warehouse, or cancelled. Submit numeric `actual_received_amount` and optional notes. Backend calculates expected delivered COD and discrepancy, creates a verified cash closure and treasury entry.

Cash closure CRUD/list routes are `/api/driver-cash-closures`; `POST /api/drivers/{driver}/cash-closures/generate` is also registered. Statuses are `pending|verified|cancelled`. Treat “complete payout/cash close” as a high-impact confirmation action and refresh driver, manifest, treasury, reconciliation, and reports.

## 32. Warehouse foundations

Warehouse is a fulfilment ledger, not the company service catalog. Core warehouse CRUD is `/api/warehouses`; KPI and scan utilities are `/api/warehouse/kpis` and `/api/warehouse/barcode/scan`. Warehouse status is `active|inactive`.

Warehouse form fields include name, unique code, address, governorate/area with relationship validation, status, and notes. The expected physical hierarchy is Warehouse → optional Zone → optional Shelf → Bin. Products may be stocked by warehouse/bin, and each merchant owns its own product catalog.

## 33. Warehouse locations

CRUD route families: `/api/warehouse-zones`, `/api/warehouse-shelves`, `/api/warehouse-bins`; all use `warehouse_locations.*`. Zone requires warehouse/name/code. Shelf requires warehouse/name/code and optional `warehouse_zone_id`. Bin requires warehouse/name/code, optional zone/shelf/barcode; barcode is generated if absent. Status is `active|inactive`, notes nullable.

Codes are unique inside a warehouse. Zone, shelf, and bin hierarchy must belong to the selected warehouse; a shelf must match the selected zone when supplied. Bin has a label endpoint. Dependent selectors must reset descendants whenever the parent changes.

## 34. Warehouse products

CRUD is `/api/warehouse-products`, with list/detail, low-stock, barcode lookup/generation, individual label, bulk labels, and universal label routes. Fields include merchant, name, per-merchant unique SKU, globally unique/generated barcode and barcode type, description, dimensions/weight, operational booleans, `active|inactive`, minimum stock/low-stock alert, and notes according to Store/Update requests.

Distinctions:

- Company catalog item: platform-sold packaging/service definition and price.
- Warehouse product: merchant-owned SKU master.
- Inventory stock: quantities of one warehouse product at a warehouse/bin (`available`, `reserved`, `damaged`).

Never use company catalog IDs in stock actions or warehouse product IDs as packaging catalog IDs.

## 35. Inventory

`GET /api/inventory-stocks`, `/low-stock`, `GET /{stock}`, and `POST /adjust` provide admin inventory. Merchant equivalents are `/api/merchant-portal/warehouse/inventory`, `/low-stock`, and `/{stock}`. Stock movements are list/detail at `/api/stock-movements`; merchant movement list is scoped. Reservations list is `/api/stock-reservations` or per order.

`on_hand = available + reserved`; damaged is separate. Adjustment submits warehouse, product, optional bin, a StockMovement type, quantity ≥1, and notes. Movement types are `in`, `out`, `reserved`, `released`, `damaged`, `adjustment`, `stock_in`, `return_received`, `return_restocked`, `return_damaged`, `return_rejected`, `stock_transfer_out`, `stock_transfer_in`. Backend rejects an impossible negative balance.

Order reserve/release/fulfil actions submit the request-defined warehouse/product/item mapping and enforce merchant ownership. Reservation statuses are `reserved|fulfilled|released|cancelled`. Always refresh stocks, movements, reservation and order fulfilment summary together.

## 36. Warehouse receipts

Routes: `GET/POST /api/warehouse-receipts`, `GET/PUT /{receipt}`, and actions `/confirm`, `/cancel`. Statuses: `draft|confirmed|cancelled`. Only draft receipts can be edited, confirmed, or cancelled.

Create fields select merchant, warehouse, notes, and item rows with warehouse product, ordered quantity, accepted quantity, rejected quantity, condition, and notes. Accepted/rejected default as implemented and their sum cannot exceed quantity. Confirm increases stock by accepted quantity. Confirm/cancel require confirmation UI and refresh inventory/movements.

### Merchant inbound stock requests

Merchant routes are list/create/show/cancel at `/api/merchant-portal/warehouse/inbound-requests`; admin routes are list/show/approve/reject/link-receipt at `/api/merchant-inbound-stock-requests`. A portal create is immediately `submitted`. It accepts optional warehouse, expected-arrival date (today or later), notes, and 1–100 items. Each item has quantity ≥1 and either an owned `warehouse_product_id`, or both a requested product name and requested SKU. Duplicate product IDs/SKUs in one request are rejected.

Statuses are `draft|submitted|approved|scheduled|partially_received|received|cancelled|rejected|closed`. Merchant cancellation is allowed in draft/submitted/approved/scheduled. Admin approval accepts only draft/submitted; review notes are internal. Admin rejection is blocked once rejected, partially/fully received, or closed. Linking a receipt requires the same merchant; a confirmed receipt changes the request to received when accepted quantity meets requested quantity, otherwise partially received. The Resource returns request number, status/label, warehouse summary, item count/total and detail rows, linked receipt summary, lifecycle timestamps, notes, and `allowed_actions.can_cancel`.

## 37. Warehouse transfers

Routes: list/create/show/update plus `/confirm`, `/dispatch`, `/receive`, `/cancel`. Lifecycle is `draft → confirmed → in_transit → received`; draft or confirmed can be cancelled. Source and destination warehouses must differ.

Items contain product, transfer quantity, optional received quantity, and notes. Dispatch checks/decrements source stock. Receive can submit item received quantities, defaults to the expected full quantity, prevents over-receipt, and increments destination stock. After each action refresh both warehouse stocks, movements, and transfer detail.

## 38. Warehouse returns

Routes: list/create/show, generic `/status`, and action endpoints `/receive`, `/inspect`, `/restock`, `/damaged`, `/reject`, `/close`. Statuses: `pending|received|inspected|restocked|damaged|rejected|closed`; conditions `sellable|damaged|disposed`.

Normal workflow is create → receive → inspect → allocate quantities to restock/damaged/reject → close. Restock increments available, damaged increments damaged stock, rejection does not restore inventory. Processing can occur repeatedly while quantities remain. Legacy single-item flows may auto-process unless deferred. The generic status endpoint can bypass normal actions; restrict that UI to explicit administrative correction and see §70.

## 39. Picking

Picking list routes: list, generate, detail, scan, cancel. Generate selects warehouse, order IDs, optional assignee and notes; eligible warehouse reservations must exist. Statuses: `pending|in_progress|completed|cancelled`; item statuses `pending|picked|partially_picked|missing`.

The scan form sends a product barcode and quantity; backend product lookup/reservation rules decide the item. Keep scan input focused, provide audible/visual success, append the server-returned item state, and block client double scans until response. Completed lists cannot be cancelled.

## 40. Packing

Packing routes: list, generate, detail, complete. Generate from a picking list creates tasks per order. Task statuses are `pending|in_progress|packed|cancelled`. Completion accepts dimensions/weight, packing fee and notes as validated, moves the task/order workflow to packed, and may create a warehouse charge.

Refresh order fulfilment, task, warehouse charges, wallet, and ready-for-dispatch queues. The backend does not clearly require a completed picking list before task generation; keep the UI conservative and see §70.

## 41. Storage and pick-pack fees

Storage rule CRUD is `/api/storage-fee-rules`; generation is `POST /api/storage-fees/generate`. Rule fields: optional merchant/pricing plan, required name and calculation type (`per_item_per_month|per_sku_per_month`), free units integer ≥0, unit price ≥0, `active|inactive`, notes. Pick-pack rule CRUD is `/api/pick-pack-fee-rules`; generation is `POST /api/pick-pack-fees/generate`. Fields: optional merchant/plan, required name, first-item pick fee, additional-item pick fee, base packing fee, fragile extra fee (all ≥0), status, notes.

Generation creates warehouse charges according to applicable rules and request period/scope. Treat generation as a confirmed, idempotency-sensitive action; display the returned counts/totals and refresh charges/wallet/reports. Merchant shop can expose applicable rule pricing but cannot edit/generate it.

## 42. Finance architecture

The merchant wallet is the merchant sub-ledger. Approved credit/debit transactions change its balance. A payout moves money from the merchant wallet through a treasury account. Expenses and driver cash closures affect treasury and reports. Invoices group approved wallet transactions for a period. Warehouse charges feed merchant debits. Treasury transactions are the immutable cash/bank/wallet/other account ledger.

Admin finance entry points include `/api/finance/reports/overview`, merchant detail/ledger, drivers, expenses, payouts, daily cash, discrepancies, warehouse revenue, profit-loss, company profit, and reconciliation. Merchant portal exposes only its summary, wallet, invoices, and payouts.

Money statuses and totals must come from server responses. Do not calculate an authoritative wallet, treasury, invoice, reconciliation, or profit value in the browser.

## 43. Merchant wallet

Admin merchant wallet endpoints: `/api/merchants/{merchant}/wallet/summary`, `/transactions`, `/deposit`, `/withdraw`; individual transaction `GET /api/merchant-wallet-transactions/{transaction}` plus `/approve` and `/reject`. Portal endpoints are `/api/merchant-portal/finance/wallet/summary`, `/transactions`, and transaction detail—read-only for system/manual entries.

Direction is `credit|debit`. Status is `pending|approved|rejected|cancelled`. Only approved transactions affect balance. Types: `cod_collected`, `shipping_fee`, `return_fee`, `swap_fee`, `storage_fee`, `pick_fee`, `packing_fee`, `warehouse_charge`, `payout`, `manual_deposit`, `manual_withdraw`, `adjustment`, `refund`, `penalty`.

Manual deposit/withdraw require amount ≥ 0.01, a reason (max 255), and optional notes/reference. Super admin or the setting `finance.wallet_manual_transactions_require_approval=false` can cause immediate approval; otherwise show pending. Approve only pending. Reject only pending and collect a reason. Refresh wallet summary, transactions, merchant finance and reports after action.

## 44. System-generated finance events

The backend creates idempotent approved/pending entries tied to source records:

| Event | Wallet effect |
|---|---|
| Delivered quantities | COD collected credit based on delivered item value |
| Delivery shipment | Shipping fee debit |
| Return/swap shipment | Applicable return/swap fee debit |
| Warehouse fee generation/packing/charge | Storage, pick, packing, or warehouse-charge debit |
| Completed payout | Payout debit |
| Approved manual action/refund/penalty | Direction/type supplied by the finance workflow |

Do not create these transactions from the frontend. After the operational mutation succeeds, refetch the finance views. Duplicate source/type records are prevented by service idempotency.

## 45. Payouts

Admin CRUD/action family: `GET/POST /api/merchant-payouts`, `GET/PUT/DELETE /{payout}`, `/approve`, `/reject`, `/complete`. Merchant: list, request, detail under `/api/merchant-portal/finance/payouts`.

Fields include merchant (admin only), optional treasury account, amount, payment method (`cash|bank_transfer|wallet|instapay|other`), reference, notes, status where internal, rejection reason, and paid timestamp where permitted. Amount cannot exceed available wallet balance for actionable states.

| Status | Allowed UI actions |
|---|---|
| `pending` | edit/delete, approve, reject |
| `approved` | complete or reject; deletion blocked |
| `completed` | view/download evidence only; wallet debit and treasury debit are final |
| `rejected` | view reason |
| `cancelled` | view |

The configured approval limit may auto-approve a newly created request when status is omitted. Completion rechecks balance. Confirm approve/reject/complete; rejection requires reason. Never optimistically show completed before the server commits both ledgers.

## 46. Expenses

Routes: `GET/POST /api/expenses`, `GET/PUT/DELETE /{expense}`, actions `/approve`, `/reject`, `/mark-paid`. Categories: `fuel|salaries|rent|packaging|maintenance|other`. Statuses: `pending|approved|paid|rejected|cancelled`. Fields include category, amount, treasury account, expense date, payment method, reference, notes, rejection reason, and paid timestamp as request permits.

Normal flow is pending → approved → paid; pending/approved can be rejected. Paid/rejected/cancelled are terminal for ordinary updates. Deleting approved/paid is blocked. Approval threshold can auto-approve. Treasury and profit/loss change only when paid. Confirm approval, rejection and payment; collect rejection reason; refresh account, transactions and reports.

## 47. Merchant invoices

Admin: `GET/POST /api/merchant-invoices`, `GET/PUT/DELETE /{invoice}`, `GET …/preview`, `GET …/download`. Portal: list/detail, preview, download under `/api/merchant-portal/finance/invoices`. Statuses: `draft|issued|paid|cancelled`.

Create selects merchant, period start/end, status and notes as validated. The backend builds invoice items from approved wallet transactions in the period. The resource returns invoice header, merchant/period, totals/status, item rows and timestamps. Preview is JSON/resource data; download returns PDF binary and needs `responseType:'blob'`. After update/create, invalidate invoice lists, detail, preview and merchant finance summary.

## 48. Treasury

Account CRUD is `/api/treasury-accounts`; transactions are read-only at `/api/treasury-transactions`. Account types: `cash|bank|wallet|other`; statuses `active|inactive`. Form fields include name, unique code, type, opening/current balance only where create permits, status and notes. An account with transactions cannot be deleted.

Transactions are generated by wallet payout completion, paid expenses, driver closure and other services; no generic create-transaction route exists. Display immutable direction/amount/balance/source/reference/timestamps returned by its Resource. Use a confirmation for account deletion and for any operation that posts a treasury movement.

## 49. Finance reports and reconciliation

All are authenticated internal reads. Base permission is `finance.reports.view`; specialized report/reconciliation slugs may additionally guard routes.

| Endpoint | Purpose | Suggested view |
|---|---|---|
| `/api/finance/reports/overview` | Wallet/payout/expense/treasury headline totals | Cards and concise trend tables |
| `/api/finance/reports/merchant/{merchant}` | One merchant's balance and activity | Merchant finance tab |
| `/api/finance/reports/drivers` | Driver COD/closure totals | Driver table |
| `/api/finance/reports/expenses` | Expense totals/categories | Summary + category table |
| `/api/finance/reports/payouts` | Payout totals/statuses | Summary + status table |
| `/api/finance/reports/daily-cash` | Daily cash movement | Date table/line chart |
| `/api/finance/reports/merchant-ledger` | Ordered wallet ledger | Ledger table |
| `/api/finance/reports/driver-discrepancies` | Expected vs actual driver cash | Exception table |
| `/api/finance/reports/warehouse-revenue` | Warehouse charge revenue | Merchant/type breakdown |
| `/api/finance/reports/profit-loss` | Revenue less paid expenses | P&L statement |
| `/api/finance/reconciliation-summary` | Cross-ledger reconciliation | Difference cards and exceptions |
| `/api/finance/company-profit-summary` | Company profit aggregation | Summary cards |

Request-supported filters include `date_from`, `date_to`, and context-dependent `merchant_id`, `driver_id`, `treasury_account_id`, and `status`; date-to must not precede date-from. Responses are aggregate JSON from `FinanceReportService`, not paginated resources. Show server-returned summaries/items, a zero-data state, applied date range, and export link where the export type supports it.

## 50. Merchant integration tokens

Admin manages tokens via `GET/POST /api/merchant-integration-tokens`, then `GET/DELETE /api/merchant-integration-tokens/{token}` and `POST …/revoke`. A merchant uses the equivalent `/api/merchant-portal/integration-tokens` routes. Providers are `woocommerce|shopify|custom`; statuses are `active|revoked|expired`.

Abilities supported by the model are `integrations.me`, `orders.create`, `orders.view`, `shipments.view`, `tracking.view`, `products.view`, and `inventory.view`. Only `orders.create` is currently exercised by an external mutation endpoint. Creation returns the plain token once; show a copy/download dialog and state that it cannot be retrieved again. List/detail must only display token metadata/hash-safe prefix, provider, abilities, expiry, last use and status. Revoke is reversible only by creating a new token; delete is destructive.

## 51. External integration API

Authentication accepts `Authorization: Bearer {plain-token}` or `X-Merchant-Token`. Invalid/revoked/expired token is 401. A global setting can disable integrations (403). Only active token plus active merchant works; rate is 120/minute per token.

`GET /api/integrations/me` returns token/merchant context. `POST /api/integrations/orders` creates a merchant-scoped order and requires `orders.create`. Its payload follows the order core fields in §20, including items, addresses, external ID, fulfilment, payment and waybill choice, but currently excludes `packaging_items`. Merchant ID/internal ownership fields are forbidden/ignored by scoping.

Use `(source, external_order_id)` as the caller's idempotency key. Duplicate values return 422 (without a stable custom code). If location/pricing is unresolved and review-on-missing is enabled, the result can be `pending_review` with zero shipping fee; callers must inspect returned status. `/integrations/me` presently lacks an explicit ability check; do not interpret that as broad API authorization.

## 52. Attachments

Both admin and merchant attachment contexts support list/upload/show/download/delete through their `/attachments` route families. Upload is `multipart/form-data` with `attachable_type`, `attachable_id`, `category`, `file`, and optional notes. Maximum file size is 10 MB; allowed types are PDF, JPG/JPEG, PNG, WEBP, CSV, XLSX.

Admin attachable contexts: merchants, orders, shipments, expenses, merchant invoices, payouts, integration tokens, inbound stock requests, company catalog items, warehouse returns, treasury transactions, and driver manifests. Merchant context is further ownership-restricted. Never expose a free-form model class selector; provide only server-supported UI contexts.

Use `FormData` and let the browser generate the multipart boundary. Download with blob handling and a sanitized filename from response metadata/header. Delete needs confirmation. Lists are paginated (default 50, max 500).

## 53. Exports

Admin endpoints: `GET/POST /api/exports`, `GET /api/exports/types`, `GET /api/exports/{export}`, `GET …/download`; merchant routes mirror them. Exports are generated synchronously as CSV and return completed metadata immediately; an empty dataset still produces a CSV message file.

Admin types: `orders`, `shipments`, `driver_manifests`, `merchant_wallet_transactions`, `wallet_transactions`, `stock_movements`, `invoices`, `payouts`, `inbound_stock_requests`, `finance_reports`, `warehouse_inventory`, `treasury_transactions`, `audit_logs`, `company_catalog_items`. Merchant exposes only its eight allowed types returned by `/types`; do not hard-code admin types in that portal.

Creation sends type plus the request-supported filters. List is paginated default 50/max 500. Download is a token-protected CSV blob. Refresh the export list after creation and handle missing/deleted export file as a normal 404.

## 54. Merchant notifications

Routes: `GET /api/merchant-portal/notifications`, `GET …/unread-count`, `POST …/mark-all-read`, `POST …/{notification}/mark-read`. Filters are optional type, `unread_only`, date range; `merchant_id` is prohibited. Lists are currently unpaginated.

Resource fields are `id`, `type`, `title`, `message`, `data` object, nullable `read_at`, and `created_at`. Bell flow: fetch unread count at bootstrap/interval, open a short list, mark a clicked item read before following a validated internal target encoded in `data`, and invalidate list/count. Mark-all-read needs no confirmation.

## 55. Settings

Admin routes: list, groups, group detail, setting detail/update, and bulk update under `/api/settings`. Public settings are `GET /api/public/settings`; portal profile settings are separate merchant-scoped endpoints. Types are `string|text|integer|decimal|boolean|json|date|time|file|image|encrypted`. Encrypted values are masked; public results exclude them. Bulk body is `{"settings":[{"key":"…","value":…}]}`. Non-editable setting update is 422.

All **92 system settings**:

| Group | Keys — default (type/public where relevant) |
|---|---|
| `company` (7) | `company_name` Fast Logistics (string/public); `company_phone` null (public); `company_email` null (public); `company_address` null (text/public); `company_tax_number` null; `company_logo` null (image/public); `company_website` null (public) |
| `finance` (8) | `default_currency` EGP (`EGP|USD|SAR`); `payout_approval_limit` 0 decimal; `expense_approval_limit` 0 decimal; `auto_approve_system_transactions` true; `invoice_prefix` INV; `invoice_due_days` 7; `wallet_manual_transactions_require_approval` true; `treasury_default_cash_account_code` CASH |
| `shipping` (6) | `default_order_status` draft (`draft|pending_review|confirmed`); `default_shipment_status` pending_pickup (`pending_pickup|driver_assigned|picked_up`); `max_delivery_attempts` 3 (1–100); `allow_partial_delivery` true; `allow_order_edit_after_shipment` false; `allow_order_delete_after_shipment` false |
| `warehouse` (6) | `low_stock_threshold` 5; `default_return_condition` sellable; `storage_fee_generate_day` 1; `auto_generate_pick_pack_fees` false; `auto_restore_sellable_returns` true; `packaging_fee_auto_charge` false |
| `merchant_shop` (4) | `enabled` true; `show_storage_fees` true; `show_pick_pack_fees` true; `show_packaging_products` true |
| `barcode` (5) | `order_prefix` ORD-BC; `shipment_prefix` SHP-BC; `warehouse_product_prefix` WH-PROD; `warehouse_bin_prefix` BIN-BC; `date_format` Ymd |
| `waybills` (3) | `prefix` WB-; `padding` 6; `max_batch_quantity` 500 |
| `labels` (21) | `default_format` html; default/product sizes 60×40; shipment 100×150; order 60×40; bin 50×30; `printer_dpi` 203 (`203|300`); `margin` 2; ZPL darkness 15/speed 4; TSPL density 8/speed 4; show company name true; show logo false; show QR false; show human-readable true |
| `notifications` (13) | email/SMS/WhatsApp enabled false; customer shipment-created/status-change false; merchant wallet/invoice false; six editable templates for shipment created/delivered/failed, wallet deposit/withdraw, invoice issued |
| `system` (7) | `timezone` Africa/Cairo (`Africa/Cairo|UTC`); `date_format` Y-m-d; `datetime_format` Y-m-d H:i:s; `pagination_limit` 15; `maintenance_mode` false; support email/phone null and public |
| `security` (4) | `password_min_length` 8; `session_timeout_minutes` 120; `force_password_change_days` null; `login_audit_enabled` true |
| `integrations` (8) | WhatsApp provider none; SMS provider none; email provider smtp; payment gateway none; token expiry null; tokens enabled true; auto-confirm orders false; review on missing location true |

Use switches for boolean, select only when options are returned, number inputs for integer/decimal, textarea for text/JSON, and text input for string/date/time as appropriate. `image`/`file` settings currently have no upload endpoint; store a path/URL string until backend clarifies. The label logo/QR, maintenance and some security settings are reserved/future hooks; see §70.

## 56. Audit logs

`GET /api/audit-logs` and `GET /api/audit-logs/{auditableType}/{auditableId}/timeline` use `audit_logs.view/timeline`. Filters include event type, auditable type/id, user, merchant, IP, date range, search and pagination as accepted by the request/service. Resource fields include id, event type, auditable reference/type, user/merchant summaries, old/new values, metadata, request/IP/user-agent context, and timestamp.

Audit event values: `merchant_registered`, `merchant_registration_approved`, `merchant_registration_rejected`, `waybill_batch_generated`, `waybill_batch_reprinted`, `waybill_assigned`, `waybill_voided`, `order_created`, `order_updated`, `order_confirmed`, `order_cancelled`, `shipment_created`, `shipment_updated`, `shipment_status_updated`, `driver_assigned`, `delivery_attempt_created`, `partial_delivery_completed`, `wallet_transaction_created`, `wallet_transaction_approved`, `wallet_transaction_rejected`, `payout_created`, `payout_approved`, `payout_rejected`, `payout_completed`, `expense_created`, `expense_approved`, `expense_rejected`, `expense_paid`, `treasury_transaction_created`, `inventory_adjusted`, `stock_reserved`, `stock_released`, `warehouse_return_created`, `picking_completed`, `packing_completed`, `manifest_generated`, `manifest_closed`, `role_updated`, `role_assigned`, `permission_updated`, `setting_updated`, `settings_bulk_updated`, `export_generated`, `attachment_uploaded`, `attachment_deleted`, `company_catalog_item_created`, `company_catalog_item_updated`, `company_catalog_item_deleted`, `order_packaging_items_updated`, `merchant_shop_previewed`, `merchant_integration_token_created`, `merchant_integration_token_revoked`, `merchant_integration_token_deleted`, `integration_token_used`, `integration_order_created`, `login`, `login_failed`, `logout`.

Render old/new JSON as a safe diff and metadata as expandable JSON. Do not allow edits/deletes. Timeline empty state means no captured events, not that the entity is absent.

## 57. Barcode scanning

Admin routes: `POST /api/barcodes/lookup`, product lookup, shipment status scan, and `POST /api/warehouse/barcode/scan` as registered. Lookup recognizes order, shipment, warehouse product, and waybill barcodes. Shipment status scan accepts shipment barcode or its assigned waybill and applies a standard allowed transition. Warehouse/picking scan accepts product barcode.

USB/Bluetooth scanners normally emulate a keyboard: keep an input focused, submit on Enter, trim whitespace, lock while awaiting response, then clear on success. Display the resource type before offering actions. Warehouse bins have generated barcodes/labels but are not recognized by the generic lookup or warehouse scan controller; do not promise bin scan navigation (gap §70).

## 58. Pagination and filters

Laravel Resource pagination shape is:

```json
{
  "data": [],
  "links": {"first":"…","last":"…","prev":null,"next":"…"},
  "meta": {"current_page":1,"from":1,"last_page":4,"links":[],"path":"…","per_page":25,"to":25,"total":88}
}
```

Only attachments (default 50/max 500), exports (50/500), audit logs (setting/service limit, max 200), settings (system pagination default/max 500), merchant registration requests (25/max 100), and waybill batches (25/max 100) paginate. Waybill batches add a top-level summary. All other current list routes call `get()` and return `{data:[…]}`; do not build pagination controls for them.

Use only request-supported filters. Common names are `search`, `status`, `merchant_id`, `date_from`, `date_to`, `page`, `per_page`; warehouse lists use warehouse/product/bin and module-specific IDs. There is no global sort contract—many lists simply use `latest()`. Preserve filters in the URL and omit blank query parameters.

## 59. Error and business-code contract

| HTTP | Meaning | Frontend action |
|---:|---|---|
| 401 | Missing/invalid Sanctum or integration token | Clear interactive auth and redirect; integrations should request a new token |
| 403 | Permission/capability or merchant operational restriction | Keep login; show forbidden/status-specific page |
| 404 | Route-bound resource/file/waybill/shipment not found | Detail not-found or inline lookup error |
| 409 | State/race conflict | Keep context, refetch entity, explain changed state |
| 422 | Form validation or domain validation | Map `errors`; show business `message`; retain values |
| 429 | Rate limit | Disable retry until `Retry-After` where available |
| 500 | Unexpected failure | Generic retry/report state; do not claim success |

Stable custom codes found in the backend:

| Code | HTTP | Meaning/action |
|---|---:|---|
| `MERCHANT_PENDING_APPROVAL` | 403 | Route to under-review page |
| `MERCHANT_REGISTRATION_REJECTED` | 403 | Show rejection page/reason |
| `MERCHANT_SUSPENDED` | 403 | Show suspension/support page |
| `MERCHANT_ACCESS_UNAVAILABLE` | 403 | Generic unavailable state |
| `MERCHANT_SELF_REVIEW_FORBIDDEN` | 403 | Reviewer cannot approve/reject own merchant |
| `INVALID_MERCHANT_REGISTRATION_TRANSITION` | 409 | Refetch; registration is no longer pending |
| `USE_MERCHANT_REGISTRATION_REVIEW` | 409 | Open dedicated review action, not generic edit |
| `WAYBILL_NOT_FOUND` | 404 | Clear/rescan entered label |
| `INVALID_WAYBILL_SOURCE` | 422 | Only preprinted waybills can be manually assigned |
| `WAYBILL_VOID` | 409 | Clear/rescan; label cannot be used |
| `WAYBILL_ALREADY_ASSIGNED` | 409 | Clear/rescan; show assigned conflict |
| `WAYBILL_NOT_AVAILABLE` | 409 | Refetch/use another number |

Many services return only a message with 409/422 and no code (invalid lifecycle, insufficient stock/balance, missing shipping rate, attempt limit, duplicate external ID). Treat message as display text and refetch authoritative state; do not branch long-term logic on English text.

## 60. TypeScript contracts

These are copy-ready structural baselines. Module Resources may return additional documented detail fields; keep API types distinct from writable form types.

```ts
type Id = number;
type Money = string; // decimal casts; format, do not float-calculate
type ISODate = string;
type ISODateTime = string;

interface ApiResource<T> { data: T }
interface PaginatedResponse<T> {
  data: T[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; from: number | null; last_page: number;
    links: unknown[]; path: string; per_page: number; to: number | null; total: number };
}
interface ValidationError {
  message: string;
  errors: Record<string, string[]>;
  code?: string;
}

type InternalRole = 'super_admin'|'admin'|'staff'|'warehouse'|'finance'|'driver'|'merchant_portal';
interface Permission { id: Id; name: string; module?: string }
interface Role { id: Id; name: string; permissions?: Permission[] }
interface User { id: Id; name: string; email: string; role?: InternalRole | Role; permissions?: string[] }

type MerchantStatus = 'pending'|'active'|'rejected'|'suspended'|'inactive';
type PortalRole = 'owner'|'admin'|'staff'|'accountant'|'warehouse_viewer'|'viewer';
type PortalStatus = 'invited'|'active'|'suspended';
interface Merchant { id: Id; company_name: string; owner_name?: string|null; email?: string|null;
  phone?: string|null; status: MerchantStatus; pricing_plan?: {id:Id;name:string}|null;
  rejection_reason?: string|null; created_at: ISODateTime; updated_at: ISODateTime }

type OrderStatus = 'draft'|'pending_review'|'confirmed'|'shipment_created'|'partially_delivered'|'delivered'|'failed'|'cancelled';
type OrderType = 'delivery'|'swap'|'return';
type PaymentType = 'cod'|'prepaid';
type FulfillmentType = 'pickup_from_merchant'|'from_warehouse'|'pickup_from_customer';
interface OrderItem { id: Id; product_name: string; sku: string|null; quantity: number;
  unit_price: Money; weight: string|null; notes: string|null }
interface Order { id: Id; order_number: string; merchant: Merchant; customer_name: string;
  customer_phone: string; order_type: OrderType; payment_type: PaymentType;
  fulfillment_type: FulfillmentType; status: OrderStatus; cod_amount: Money;
  shipping_fee: Money; total_packaging_fee: Money; items: OrderItem[];
  allowed_actions: string[]; created_at: ISODateTime; updated_at: ISODateTime }

type ShipmentStatus = 'pending_pickup'|'driver_assigned'|'picked_up'|'arrived_at_hub'|'sorting'|
  'ready_for_dispatch'|'in_transit'|'out_for_delivery'|'delivery_attempted'|'rescheduled'|
  'delivered'|'partially_delivered'|'failed'|'returned'|'returned_to_hub'|
  'returned_to_warehouse'|'cancelled';
interface Shipment { id: Id; shipment_number: string; status: ShipmentStatus;
  order: Pick<Order,'id'|'order_number'|'status'>; driver: {id:Id;name:string}|null;
  waybill: Waybill|null; created_at: ISODateTime; updated_at: ISODateTime }

type WaybillStatus = 'available'|'assigned'|'void';
type WaybillSource = 'automatic'|'preprinted';
interface Waybill { id: Id; waybill_number: string; barcode: string; status: WaybillStatus;
  source: WaybillSource; order_id: Id|null; shipment_id: Id|null; assigned_at: ISODateTime|null }

interface Warehouse { id: Id; name: string; code: string; status: 'active'|'inactive' }
interface WarehouseProduct { id: Id; merchant_id: Id; name: string; sku: string; barcode: string;
  status: 'active'|'inactive'; minimum_stock?: number }
interface InventoryStock { id: Id; warehouse: Warehouse; product: WarehouseProduct;
  available_quantity: number; reserved_quantity: number; damaged_quantity: number; on_hand_quantity: number }

type WalletDirection = 'credit'|'debit';
type FinanceStatus = 'pending'|'approved'|'rejected'|'cancelled';
interface WalletTransaction { id: Id; merchant_id: Id; type: string; direction: WalletDirection;
  amount: Money; status: FinanceStatus; reference?: string|null; created_at: ISODateTime }
interface Payout { id: Id; merchant_id: Id; amount: Money;
  status: 'pending'|'approved'|'completed'|'rejected'|'cancelled'; rejection_reason?: string|null }
interface Invoice { id: Id; merchant_id: Id; invoice_number: string; period_start: ISODate;
  period_end: ISODate; status: 'draft'|'issued'|'paid'|'cancelled'; total: Money }
```

Use generated discriminated unions for all values in §62. Do not make resource relationships non-null unless the Resource always emits them.

## 61. API client and data fetching

Recommended organization: `auth`, `users-access`, `merchants`, `catalog-pricing`, `orders`, `shipments-waybills`, `drivers`, `warehouse`, `finance`, `integrations`, and `system` API modules behind one client. The shared client sets base URL, bearer header and locale, parses JSON, normalizes validation errors, and has a separate blob response helper.

React Query-style keys and invalidation:

| Query | Key | Mutations that invalidate |
|---|---|---|
| Orders/detail | `['orders', filters]`, `['order', id]` | create/update/delete/confirm/cancel/stock/shipment |
| Shipments/detail | `['shipments', filters]`, `['shipment', id]` | assign/status/attempt/complete |
| Merchant | `['merchants', filters]`, `['merchant', id]` | CRUD/review/member changes |
| Warehouse stock | `['inventory', filters]` | adjust/receipt/transfer/return/reserve/pick/pack |
| Wallet | `['wallet', merchantId, filters]` | approval/deposit/withdraw/payout/operational finance event |
| Waybills | `['waybill-batches', filters]`, `['waybill', id]` | batch generation/assignment/void |
| Session | `['auth','me']`, `['merchant-portal','me']` | login/logout/profile/membership/status change |

Also invalidate the relevant dashboard and report queries after domain mutations. A 401 handler should run once, clear private cache, then redirect. A 403 handler must not clear auth. Cancel in-flight private queries during logout/merchant switching (even though current middleware expects exactly one membership).

## 62. Status badges and display conventions

Use the exact value as the API value; labels below are suggestions only.

| Group | Exact values (suggested label = title-cased value) |
|---|---|
| Driver | `active`, `inactive` |
| Manifest | `open`, `closed`, `cancelled` |
| Cash closure | `pending`, `verified`, `cancelled` |
| Expense | `pending`, `approved`, `paid`, `rejected`, `cancelled` |
| Invoice | `draft`, `issued`, `paid`, `cancelled` |
| Wallet transaction | `pending`, `approved`, `rejected`, `cancelled` |
| Payout | `pending`, `approved`, `completed`, `rejected`, `cancelled` |
| Treasury | `active`, `inactive` |
| Merchant | `pending`, `active`, `rejected`, `suspended`, `inactive` |
| Portal membership | `invited`, `active`, `suspended` |
| Inbound request | `draft`, `submitted`, `approved`, `scheduled`, `partially_received`, `received`, `cancelled`, `rejected`, `closed` |
| Order | `draft`, `pending_review`, `confirmed`, `shipment_created`, `partially_delivered`, `delivered`, `failed`, `cancelled` |
| Order item delivery | `pending`, `reserved`, `fulfilled`, `partially_delivered`, `delivered`, `returned`, `damaged`, `cancelled` |
| Warehouse fulfilment | `not_required`, `pending_reservation`, `reserved`, `picking`, `picked`, `packing`, `packed`, `ready_for_dispatch`, `fulfilled`, `cancelled` |
| Shipment | all 18 values in `ShipmentStatus` in §60 |
| Delivery item result | `delivered`, `returned`, `damaged`, `cancelled` |
| Product/warehouse/location/rule | `active`, `inactive` |
| Receipt | `draft`, `confirmed`, `cancelled` |
| Transfer | `draft`, `confirmed`, `in_transit`, `received`, `cancelled` |
| Return | `pending`, `received`, `inspected`, `restocked`, `damaged`, `rejected`, `closed` |
| Picking list | `pending`, `in_progress`, `completed`, `cancelled` |
| Picking item | `pending`, `picked`, `partially_picked`, `missing` |
| Packing task | `pending`, `in_progress`, `packed`, `cancelled` |
| Reservation | `reserved`, `fulfilled`, `released`, `cancelled` |
| Warehouse charge | `pending`, `invoiced`, `paid`, `cancelled` |
| Integration token | `active`, `revoked`, `expired` |
| Waybill | `available`, `assigned`, `void` |

Other exact enum groups: expense category in §46; wallet type/direction and payment method in §§43/45; order type/payment/fulfilment/source in §20; attempt reason/result in §24; return condition in §23; stock movement in §35; storage calculation in §41; label format/type in §29; treasury type in §48; waybill source in §26; portal/internal roles in §§5/60; catalog types in §16. Across these, the repository contains **53 enum/status source files/groups**.

Format currency using `finance.default_currency`, with two decimals and locale-aware separators. Treat null as “Not provided,” not zero. Use the configured display date/time formats and timezone label, but parse API timestamps as absolute/serialized timestamps rather than assuming they are Cairo-local.

## 63. Recommended frontend routes

| Frontend route family | Auth/guard | Purpose |
|---|---|---|
| `/admin/login` | public | Internal login |
| `/admin/dashboard` | `dashboard.view` | Internal summary |
| `/admin/users`, `/roles`, `/permissions` | matching access permissions | Accounts/access control |
| `/admin/merchants`, `/:id/*`, `/merchant-registrations` | merchant/review permissions | Merchant profile, review, users, finance, inventory, integration tabs |
| `/admin/catalog`, `/pricing-plans`, `/shipping-rates`, `/locations/*` | catalog/pricing/location permissions | Reference configuration |
| `/admin/orders`, `/orders/new`, `/orders/:id` | orders permissions | Orders and warehouse flow |
| `/admin/shipments`, `/:id`, `/tracking` | shipment permissions/public | Shipment operation/tracking |
| `/admin/waybills/batches`, `/:id`, `/waybills/:id` | waybill permissions | Generate/validate/print/void |
| `/admin/drivers`, `/:id`, `/manifests`, `/:id` | driver/manifest permissions | Last-mile and cash close |
| `/admin/warehouse/{warehouses,locations,products,inventory,receipts,transfers,returns,picking,packing,charges,fee-rules}` | matching warehouse permission | Full warehouse suite |
| `/admin/finance/{wallet,payouts,expenses,invoices,treasury,cash-closures,reports,reconciliation}` | matching finance permission | Finance suite |
| `/admin/system/{settings,audit,attachments,exports,labels,barcode}` | matching permission | System utilities |
| `/merchant/register`, `/login` | public | Merchant onboarding/auth |
| `/merchant/account-under-review`, `/registration-rejected`, `/suspended` | authenticated safe account | Operational state pages |
| `/merchant/dashboard`, `/orders`, `/orders/new`, `/orders/:id`, `/shipments/:id` | portal capabilities | Merchant operations |
| `/merchant/warehouse/{products,inventory,inbound,charges}` | portal capabilities | Scoped warehouse reads/requests |
| `/merchant/finance`, `/wallet`, `/invoices`, `/payouts` | finance capabilities | Scoped finances |
| `/merchant/{integrations,exports,attachments,notifications,team,profile,settings}` | corresponding capability | Portal tools/account |
| `/merchant/shop` | `store.view`, but blocked | No callable portal API exists; feature-flag pending backend route |
| `/track/:shipmentNumber` | public | Public tracking |

Detail/edit views may be nested tabs rather than separate URLs, but guards must apply at both route and action level.

## 64. Admin versus merchant API matrix

| Feature | Admin API | Merchant API | Difference |
|---|---|---|---|
| Dashboard | `/api/dashboard/summary` | `/api/merchant-portal/dashboard/summary` | Global vs current merchant |
| Orders | `/api/orders*` | `/api/merchant-portal/orders*` | Admin chooses merchant; portal cannot |
| Shipments | `/api/shipments*` | `/api/merchant-portal/shipments*` | Admin mutates status/delivery; portal reads/prints |
| Locations | `/api/governorates`, `/areas` CRUD | `/merchant-portal/locations/*` read | Portal lookup only |
| Warehouse | `/api/warehouse-*`, inventory/process routes | `/merchant-portal/warehouse/*` | Portal inventory/read and inbound requests only |
| Wallet | merchant admin routes + transaction actions | `/merchant-portal/finance/wallet/*` | Portal read-only |
| Payouts | full CRUD/workflow | list/request/detail | Portal cannot approve/complete |
| Invoices | create/update/delete/preview/download | list/detail/preview/download | Portal read/download |
| Integrations | cross-merchant token routes | scoped token/docs/test | Plain token once in both creation contexts |
| Attachments | broad attachable contexts | owned contexts only | Same file constraints, scoped ownership |
| Exports | 14 types | returned subset of 8 | Never use admin type list in portal |
| Notifications | no internal notification center | portal-only routes | Merchant scoped |
| Settings | system settings | merchant profile/settings | Different models/contracts |
| Shop | catalog/pricing + merchant preview | none currently | `store.view` exists, but a scoped portal endpoint is missing |

Never call an admin endpoint from the merchant shell even if the logged-in user happens to have a similarly named capability.

## 65. Read, mutation, and destructive actions

Classify client functions by intent: GET list/show/summary/preview/validate = `READ`; POST store/generate = `CREATE`; PUT/PATCH = `UPDATE`; DELETE = `DELETE`; lifecycle POST/PATCH = `ACTION`; label/print = `PRINT`; file response = `DOWNLOAD`; multipart store = `UPLOAD`.

Require a modal for delete, order/receipt/transfer/inbound cancellation, registration/payout/expense/wallet rejection, waybill void, stock adjustment, payout completion, expense payment, manifest close, return reject/damaged/restock, transfer dispatch/receive, and fee generation. Display the exact selected entity, state and amount/quantity. Where the request accepts/requires a reason—registration rejection, wallet/payout/expense rejection, waybill void—make it part of the confirmation form.

## 66. Download, print, and upload contract

| Asset | Endpoint family | Output/auth | Handling |
|---|---|---|---|
| Invoice PDF | invoice `/download` | PDF, bearer/capability | blob → object URL/download |
| Order/shipment/product/bin/waybill label | resource `/label` | JSON/HTML/PDF/ZPL/TSPL as supported, bearer | choose handler by content type |
| Simple waybill/batch simple | `/simple-label`, `/print/simple` | same supported formats | contains number/barcode only |
| Manifest print | manifest `/print` | HTML/PDF/JSON reliably | protected preview/blob |
| Bulk labels | order/shipment/product bulk endpoints | selected format | blob/text/JSON |
| Export | export `/download` | CSV, bearer | blob/download filename |
| Attachment | attachment `/download` | original content type, bearer | blob/download filename |
| Attachment upload | attachment POST | multipart, bearer/permission | `FormData`; progress optional |

Because bearer headers are required, do not use a raw `<a href>` for protected downloads unless the application has another safe authenticated transport. Fetch first. Revoke object URLs after use. Never render returned HTML without trusting the backend deployment and an appropriate CSP.

## 67. Form field reference

| Form | Principal fields/input controls | Important validation/action |
|---|---|---|
| Merchant registration | company/owner text, email, phone, password+confirmation, address, governorate→area | password min 8; unique email; area belongs |
| Merchant admin | identity/contact/address, plan select, status select, notes | use dedicated review for registrations |
| Order | grouped fields in §20, repeatable items/packaging, waybill radio+scan | conditional type/fulfilment/waybill validation |
| Driver | linked user select, name/phone, national ID, vehicle, status, notes | name/phone required |
| Warehouse | name/code/address, governorate→area, status, notes | unique code; area belongs |
| Zone/Shelf/Bin | hierarchy selects, name/code, optional barcode, status, notes | descendants belong; code unique per warehouse |
| Warehouse product | merchant, name/SKU/barcode, description, measures, flags, minimum stock, status | SKU per merchant; barcode global |
| Stock adjustment | warehouse/product/bin, movement type, quantity, notes | quantity ≥1; cannot make balance invalid |
| Receipt | merchant, warehouse, repeatable product quantities/accept/reject/condition | accepted+rejected ≤ quantity |
| Transfer | source/destination, repeatable product quantities | warehouses differ; receive ≤ sent |
| Return process | product/quantity, condition, restock/damaged/reject allocation, notes | cannot exceed remaining |
| Waybill batch | quantity number, template, notes | 1..`waybills.max_batch_quantity` |
| Shipment completion | overall status, item outcome rows/quantities/return condition, notes | derived overall status must match |
| Manifest | driver, date, shipment multiselect, notes | eligible shipments, no open duplicate |
| Wallet manual entry | amount, reason, notes/reference | amount ≥.01; may require approval |
| Payout | merchant/treasury, amount, payment method, reference, notes | available balance; workflow rules |
| Expense | category, amount, treasury, date, payment method, reference, notes | paid posts treasury |
| Invoice | merchant, start/end, status, notes | period order; items generated by backend |
| Catalog item | all §16 fields | exact type/status and unique SKU |
| Integration token | merchant/admin context, name/provider, abilities, expiry | token shown once |
| Attachment | context/entity/category, file, notes | multipart; allowed extensions; 10 MB |
| Setting | control by returned type/options | editable flag; bulk key/value rows |

Use the live FormRequest error response as final field-level authority. Never pre-submit a field merely because a Resource returns it; resources contain read-only IDs, totals, audit metadata, and relationships.

## 68. Screen implementation checklist

### Admin/Super Admin

- [ ] Login, auth bootstrap, logout, 401/403/404/409/422/429/500 states
- [ ] Permission-aware shell, dashboard
- [ ] Users list/create/detail/edit/delete/assign role
- [ ] Roles list/create/detail/edit/delete/permission matrix
- [ ] Merchants list/create/detail/edit/delete; dashboard/finance/inventory/movements/charges/shop/tokens/users tabs
- [ ] Registration list/detail/approve/reject
- [ ] Company catalog list/editor; merchant shop preview
- [ ] Governorates and areas CRUD
- [ ] Pricing plans and plan rates CRUD; shipping rates CRUD/calculate/restore/force-delete
- [ ] Orders list/create/detail/edit/delete/confirm/create shipment/cancel-state/stock/warehouse actions/label
- [ ] Shipments list/detail/assign/status/attempt/complete delivery/label/waybill/bulk print/print list
- [ ] Public tracking utility
- [ ] Waybill batch list/generate/detail/full/simple print; validate; label/simple label; void
- [ ] Label settings/templates/preview/test and bulk print UI
- [ ] Drivers list/create/detail/edit/delete/shipments/manifest/expected cash
- [ ] Manifest list/generate/detail/print/close; cash closure list/detail/create/edit/delete/generate
- [ ] Warehouses, zones, shelves and bins CRUD; bin label; KPI dashboard/scanner
- [ ] Warehouse products CRUD, low stock, barcode generate/lookup, individual/bulk/universal labels
- [ ] Inventory list/detail/low stock/adjust; movements; reservations
- [ ] Receipts list/create/detail/edit/confirm/cancel
- [ ] Transfers list/create/detail/edit/confirm/dispatch/receive/cancel
- [ ] Returns list/create/detail/receive/inspect/restock/damaged/reject/close/admin status correction
- [ ] Picking list/generate/detail/scan/cancel
- [ ] Packing task list/generate/detail/complete
- [ ] Inbound stock request list/detail/approve/reject/link receipt
- [ ] Warehouse charges CRUD; storage and pick-pack rule CRUD/generation
- [ ] Wallet summary/transactions/detail/deposit/withdraw/approve/reject
- [ ] Payouts CRUD/approve/reject/complete
- [ ] Expenses CRUD/approve/reject/mark paid
- [ ] Invoices list/create/detail/edit/delete/preview/download
- [ ] Treasury accounts CRUD and transactions read-only
- [ ] Every finance/reconciliation report in §49
- [ ] Integration tokens list/create/one-time secret/detail/revoke/delete
- [ ] Attachments panel/upload/download/delete
- [ ] Export types/create/history/detail/download
- [ ] Settings group/list/detail/single/bulk editor and public branding bootstrap
- [ ] Audit list, filters, JSON diff, entity timeline
- [ ] Barcode universal/product/shipment-status scanner

### Merchant Portal/Public

- [ ] Registration, login, auth bootstrap and owner creation success
- [ ] Pending, rejected-reason, suspended and unavailable account states
- [ ] Dashboard, profile edit, settings edit, logout
- [ ] Team list/create/detail/edit/delete/activate/suspend with role capability guards
- [ ] Order list/create/detail/edit/confirm/cancel/label including packaging and waybill choice
- [ ] Shipment list/detail/tracking/label/waybill
- [ ] Warehouse products/inventory/low-stock/movements/charges read views
- [ ] Inbound request list/create/detail/cancel
- [ ] Finance summary, wallet summary/transactions/detail
- [ ] Invoice list/detail/preview/download
- [ ] Payout list/request/detail
- [ ] Integration token list/create/one-time secret/detail/revoke/delete; docs/test
- [ ] Export types/create/history/detail/download
- [ ] Attachment list/upload/detail/download/delete
- [ ] Notification bell/list/unread/mark one/all read
- [ ] Read-only merchant shop/catalog/pricing (blocked until a scoped portal endpoint exists)
- [ ] Location lookups, public settings/branding, public tracking

## 69. Dependencies and implementation order

Dependency map:

```text
Auth → identity/permissions/capabilities → app shells
Locations + merchants + pricing + catalog + waybill validation → order create
Confirmed order + warehouse fulfilment + waybill → shipment create
Inventory reservation → picking → packing → ready for dispatch
Driver + eligible shipments → manifest → delivery outcomes → cash closure
Delivery/warehouse events → wallet/charges → invoice/payout/reports/reconciliation
```

Recommended build order: (1) shared client/errors/auth/public settings; (2) access control and shells; (3) locations, users, roles, merchants and registration; (4) catalog/pricing/orders/waybills; (5) shipments/drivers/manifests/tracking/labels; (6) warehouse master data, stock and fulfilment; (7) finance ledgers/workflows/reports; (8) merchant portal parity, notifications, integrations, exports and attachments; (9) settings/audit and full destructive/file accessibility polish.

## 70. Backend questions, gaps, and traceability

Do not invent frontend behavior for these observed gaps:

| Feature | Observed backend behavior | Frontend decision / clarification needed |
|---|---|---|
| Internal user suspension | User model/login has no active/suspended enforcement | Do not build an “inactive login” promise without backend support |
| Driver portal | Driver may link a User but no dedicated driver API/shell exists | Build driver admin records only unless a driver product is specified |
| Merchant shop portal | `store.view` capability exists, but only internal permission-protected `/api/merchant-shop/*` and admin preview routes are registered | Feature-flag the merchant shop until a merchant-scoped route is added |
| Order settings | `allow_order_edit_after_shipment` and `allow_order_delete_after_shipment` defaults exist, but lifecycle code enforces its own blocking rules | Follow actual API rejection; clarify whether settings should be consumed |
| Password/security settings | Setting default is min 8, but some admin/portal requests validate min 6; session timeout/forced change are future hooks | Follow each request today; label unsupported controls clearly |
| Maintenance/label options | Maintenance mode and label logo/QR flags are reserved/not fully consumed | Do not promise behavior merely because a setting exists |
| File/image settings | Types and company logo/catalog image path exist, but no generic upload route | Accept URL/path only or request an upload contract |
| Bin scanning | Bin labels/barcodes exist but barcode lookup and warehouse scan do not resolve bins | Do not implement bin scan navigation yet |
| Manifest raw formats | Manifest renderer reliably handles HTML/PDF/JSON; ZPL/TSPL fall back to JSON | Hide raw-format choice for manifests |
| Packing eligibility | Task generation does not explicitly require picking status `completed` | UI should require completion; backend should confirm/enforce |
| Warehouse return `/status` | Generic action can bypass the detailed inventory-safe workflow | Restrict to privileged correction and prefer named actions |
| Admin order cancel | No dedicated action; generic update can set status where lifecycle allows | Use explicit status update only; clarify whether a cancel endpoint is desired |
| Integration abilities | Model lists seven abilities, but only `/me` and order create exist; `/me` has no explicit ability check | Display abilities, but do not advertise absent endpoints |
| Duplicate external orders | Returns 422 without stable custom code | Show server error; backend should add a code/idempotent replay contract |
| Pagination | Most large operational lists are unpaginated | Frontend cannot implement true server pagination; backend may need it at scale |
| Sorting | No global sort query contract | Use server order; do not send speculative sort fields |

Useful maintenance sources: routes in `routes/api.php` and `app/Modules/*/Routes/api.php`; permissions in `app/Modules/RolePermission/Support/RolePermissionDefaults.php`; settings in `app/Modules/Settings/Support/SystemSettingDefaults.php`; portal isolation/capabilities in MerchantPortal middleware/services; state transitions in Order, Shipment, Warehouse, Driver and Finance services; waybill sequence/assignment services; API Resources for response shapes; FormRequests for writable fields.

### Complete registered API route-family index

This index is a coverage cross-check. “CRUD” means the exact registered `GET collection`, `POST collection`, `GET member`, `PUT/PATCH member`, and `DELETE member` combination supported by that family—not permission to assume missing verbs.

| Family (registered route count) | Methods and paths covered by the contracts above |
|---|---|
| Auth (4) | `POST auth/login`, `POST auth/logout`, `GET auth/me`, `GET auth/ping` |
| Public/integrations (4) | `GET public/settings`, `GET track/{shipment_number}`, `GET integrations/me`, `POST integrations/orders` |
| Merchant portal (73) | attachments (5); dashboard (1); exports (5); finance invoices (4), payouts (4), summary (1), wallet (3); integration tokens (5), docs/test (2); locations (2); me (1); notifications (4); orders (7); profile (2); register (1); settings (2); shipments (5); users (7); warehouse charges (1), inbound requests (5), inventory (3), movements (1), products/labels (5) |
| Areas/governorates (10) | CRUD `/areas`, CRUD `/governorates` |
| Attachments/audit/barcodes (12) | attachments list/store/show/delete/download (5); audit list/timeline (2); barcode lookup/product/status (3); stock-movement list/show (2) |
| Catalog/dashboard (6) | catalog CRUD (5); dashboard summary (1) |
| Drivers (20) | delivery-attempt list (1); cash-closure CRUD plus driver generate (6); manifest list/generate/show/close/print (5); driver CRUD plus shipments/manifest/cash-expected (8) |
| Expenses/exports (12) | expense CRUD plus approve/reject/mark-paid (8); export list/show/download/create-by-type (4) |
| Finance reports (12) | company profit, reconciliation, daily cash, discrepancies, drivers, expenses, merchant ledger, merchant detail, overview, payouts, profit-loss, warehouse revenue |
| Inventory/labels (8) | stocks list/show/low-stock/adjust (4); label settings/templates/preview/test (4) |
| Admin inbound/tokens (8) | inbound list/show/approve/reject/link-receipt (5); merchant integration token list/create (2); merchant-specific token listing/action routes represented in merchant family |
| Invoices/payouts (15) | invoices CRUD/download/preview (7); payouts CRUD/approve/reject/complete (8) |
| Registrations/shop (6) | registration list/show/approve/reject (4); merchant shop catalog/pricing (2) |
| Merchant finance/admin (14) | wallet transaction show/approve/reject (3); merchant wallet deposit/withdraw/summary/transactions (4); merchant dashboard/finance/shop/warehouse tab endpoints as detailed (7) |
| Merchants and portal users (24) | merchant CRUD and detail tabs; integration tokens; portal-user CRUD/activate/suspend; shop preview; warehouse charges/inventory/movements |
| Orders (17) | CRUD; confirm; `POST {order}/shipments`; reserve/release/`fulfill-from-warehouse`; reservations; label; five warehouse workflow actions |
| Picking/packing (9) | picking list/generate/show/scan/cancel (5); packing list/generate/show/complete (4) |
| Pricing (23) | pricing plans CRUD (5); plan rates CRUD (5); shipping rates list/create/show/update/delete/calculate/force-delete/restore (8); governorate/area CRUD counted above |
| Access/settings (20) | roles CRUD/sync (6); permissions list/grouped (2); users CRUD/assign role (6); settings list/groups/group/show/update/bulk (6) |
| Shipments (10) | list/show, bulk labels/print-list, assign, complete delivery, attempt, label, status, waybill |
| Warehouse fee/stock (19) | stock reservations list (1); storage rules CRUD + generate (6); pick-pack rules CRUD + generate (6); warehouse charges CRUD (5); warehouse KPI (1) |
| Treasury (6) | accounts CRUD (5); transaction list (1) |
| Warehouse structure/products (35) | warehouse CRUD (5); zone CRUD (5); shelf CRUD (5); bin CRUD+label (6); products CRUD+low-stock/barcode/generate/labels/bulk/universal (12); barcode scan and KPI (2) |
| Warehouse operations (32) | receipts list/create/show/update/confirm/cancel (6); returns list/create/show/status/receive/inspect/restock/damaged/reject/close (10); transfers list/create/show/update/confirm/dispatch/receive/cancel (8); picking and packing counted above |
| Waybills (9) | batches list/create/show/print/simple-print (5); validate/label/simple-label/void (4) |

The family counts reconcile to the registered route inventory when overlapping explanatory rows are counted only in their named route group. Use `php artisan route:list --json` as the automated regression check when routes change; update this guide whenever that check changes.

### Exhaustive registered API method/path registry

This compact registry is generated from the live route table. `GET|HEAD` is written as `GET`; clients should issue GET. Contracts, inputs, permissions, states, and file behavior are in the preceding sections.

#### Authentication (4)

```text
POST /api/auth/login
POST /api/auth/logout
GET /api/auth/me
GET /api/auth/ping
```

#### Merchant portal (73)

```text
GET /api/merchant-portal/attachments
POST /api/merchant-portal/attachments
GET /api/merchant-portal/attachments/{attachment}
DELETE /api/merchant-portal/attachments/{attachment}
GET /api/merchant-portal/attachments/{attachment}/download
GET /api/merchant-portal/dashboard/summary
POST /api/merchant-portal/exports
GET /api/merchant-portal/exports
GET /api/merchant-portal/exports/types
GET /api/merchant-portal/exports/{export}
GET /api/merchant-portal/exports/{export}/download
GET /api/merchant-portal/finance/invoices
GET /api/merchant-portal/finance/invoices/{invoice}
GET /api/merchant-portal/finance/invoices/{invoice}/download
GET /api/merchant-portal/finance/invoices/{invoice}/preview
GET /api/merchant-portal/finance/payouts
POST /api/merchant-portal/finance/payouts/request
GET /api/merchant-portal/finance/payouts/{payout}
GET /api/merchant-portal/finance/summary
GET /api/merchant-portal/finance/wallet/summary
GET /api/merchant-portal/finance/wallet/transactions
GET /api/merchant-portal/finance/wallet/transactions/{transaction}
GET /api/merchant-portal/integration-tokens
POST /api/merchant-portal/integration-tokens
GET /api/merchant-portal/integration-tokens/{token}
DELETE /api/merchant-portal/integration-tokens/{token}
POST /api/merchant-portal/integration-tokens/{token}/revoke
GET /api/merchant-portal/integrations/docs
POST /api/merchant-portal/integrations/test
GET /api/merchant-portal/locations/areas
GET /api/merchant-portal/locations/governorates
GET /api/merchant-portal/me
GET /api/merchant-portal/notifications
POST /api/merchant-portal/notifications/mark-all-read
GET /api/merchant-portal/notifications/unread-count
POST /api/merchant-portal/notifications/{notification}/mark-read
GET /api/merchant-portal/orders
POST /api/merchant-portal/orders
GET /api/merchant-portal/orders/{order}
PUT /api/merchant-portal/orders/{order}
POST /api/merchant-portal/orders/{order}/cancel
POST /api/merchant-portal/orders/{order}/confirm
GET /api/merchant-portal/orders/{order}/label
GET /api/merchant-portal/profile
PUT /api/merchant-portal/profile
POST /api/merchant-portal/register
GET /api/merchant-portal/settings
PUT /api/merchant-portal/settings
GET /api/merchant-portal/shipments
GET /api/merchant-portal/shipments/{shipment}
GET /api/merchant-portal/shipments/{shipment}/label
GET /api/merchant-portal/shipments/{shipment}/tracking
GET /api/merchant-portal/shipments/{shipment}/waybill
GET /api/merchant-portal/users
POST /api/merchant-portal/users
GET /api/merchant-portal/users/{merchantUser}
PUT /api/merchant-portal/users/{merchantUser}
DELETE /api/merchant-portal/users/{merchantUser}
POST /api/merchant-portal/users/{merchantUser}/activate
POST /api/merchant-portal/users/{merchantUser}/suspend
GET /api/merchant-portal/warehouse/charges
GET /api/merchant-portal/warehouse/inbound-requests
POST /api/merchant-portal/warehouse/inbound-requests
GET /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}
POST /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}/cancel
GET /api/merchant-portal/warehouse/inventory
GET /api/merchant-portal/warehouse/inventory/low-stock
GET /api/merchant-portal/warehouse/inventory/{inventoryStock}
GET /api/merchant-portal/warehouse/movements
GET /api/merchant-portal/warehouse/products
POST /api/merchant-portal/warehouse/products/labels/bulk
GET /api/merchant-portal/warehouse/products/{warehouseProduct}
GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label
```

#### External integrations (2)

```text
GET /api/integrations/me
POST /api/integrations/orders
```

#### Public (2)

```text
GET /api/public/settings
GET /api/track/{shipment_number}
```

#### Internal admin/general (303)

```text
GET /api/areas
POST /api/areas
GET /api/areas/{area}
PUT /api/areas/{area}
DELETE /api/areas/{area}
GET /api/attachments
POST /api/attachments
GET /api/attachments/{attachment}
DELETE /api/attachments/{attachment}
GET /api/attachments/{attachment}/download
GET /api/audit-logs
GET /api/audit-logs/timeline
POST /api/barcodes/lookup
POST /api/barcodes/scan/product-lookup
POST /api/barcodes/scan/shipment-status
GET /api/company-catalog-items
POST /api/company-catalog-items
GET /api/company-catalog-items/{companyCatalogItem}
PUT /api/company-catalog-items/{companyCatalogItem}
DELETE /api/company-catalog-items/{companyCatalogItem}
GET /api/dashboard/summary
GET /api/delivery-attempts
GET /api/driver-cash-closures
POST /api/driver-cash-closures
GET /api/driver-cash-closures/{driverCashClosure}
PUT /api/driver-cash-closures/{driverCashClosure}
DELETE /api/driver-cash-closures/{driverCashClosure}
GET /api/driver-manifests
POST /api/driver-manifests/generate
GET /api/driver-manifests/{manifest}
POST /api/driver-manifests/{manifest}/close
GET /api/driver-manifests/{manifest}/print
GET /api/drivers
POST /api/drivers
GET /api/drivers/{driver}
PUT /api/drivers/{driver}
DELETE /api/drivers/{driver}
POST /api/drivers/{driver}/cash-closures/generate
GET /api/drivers/{driver}/cash-expected
GET /api/drivers/{driver}/manifest
GET /api/drivers/{driver}/shipments
GET /api/expenses
POST /api/expenses
GET /api/expenses/{expense}
PUT /api/expenses/{expense}
DELETE /api/expenses/{expense}
POST /api/expenses/{expense}/approve
POST /api/expenses/{expense}/mark-paid
POST /api/expenses/{expense}/reject
GET /api/exports
GET /api/exports/{exportLog}
GET /api/exports/{exportLog}/download
POST /api/exports/{type}
GET /api/finance/company-profit-summary
GET /api/finance/reconciliation-summary
GET /api/finance/reports/daily-cash
GET /api/finance/reports/driver-discrepancies
GET /api/finance/reports/drivers
GET /api/finance/reports/expenses
GET /api/finance/reports/merchant-ledger
GET /api/finance/reports/merchant/{merchant}
GET /api/finance/reports/overview
GET /api/finance/reports/payouts
GET /api/finance/reports/profit-loss
GET /api/finance/reports/warehouse-revenue
GET /api/governorates
POST /api/governorates
GET /api/governorates/{governorate}
PUT /api/governorates/{governorate}
DELETE /api/governorates/{governorate}
GET /api/inventory-stocks
POST /api/inventory-stocks/adjust
GET /api/inventory-stocks/low-stock
GET /api/inventory-stocks/{inventoryStock}
POST /api/labels/preview
GET /api/labels/settings
GET /api/labels/templates
POST /api/labels/test
GET /api/merchant-inbound-stock-requests
GET /api/merchant-inbound-stock-requests/{inboundRequest}
POST /api/merchant-inbound-stock-requests/{inboundRequest}/approve
POST /api/merchant-inbound-stock-requests/{inboundRequest}/link-warehouse-receipt
POST /api/merchant-inbound-stock-requests/{inboundRequest}/reject
GET /api/merchant-integration-tokens/{token}
DELETE /api/merchant-integration-tokens/{token}
POST /api/merchant-integration-tokens/{token}/revoke
GET /api/merchant-invoices
POST /api/merchant-invoices
GET /api/merchant-invoices/{merchantInvoice}
PUT /api/merchant-invoices/{merchantInvoice}
DELETE /api/merchant-invoices/{merchantInvoice}
GET /api/merchant-invoices/{merchantInvoice}/download
GET /api/merchant-invoices/{merchantInvoice}/preview
GET /api/merchant-payouts
POST /api/merchant-payouts
GET /api/merchant-payouts/{merchantPayout}
PUT /api/merchant-payouts/{merchantPayout}
DELETE /api/merchant-payouts/{merchantPayout}
POST /api/merchant-payouts/{merchantPayout}/approve
POST /api/merchant-payouts/{merchantPayout}/complete
POST /api/merchant-payouts/{merchantPayout}/reject
GET /api/merchant-registration-requests
GET /api/merchant-registration-requests/{merchant}
POST /api/merchant-registration-requests/{merchant}/approve
POST /api/merchant-registration-requests/{merchant}/reject
GET /api/merchant-shop/catalog
GET /api/merchant-shop/pricing
GET /api/merchant-wallet-transactions/{transaction}
POST /api/merchant-wallet-transactions/{transaction}/approve
POST /api/merchant-wallet-transactions/{transaction}/reject
GET /api/merchants
POST /api/merchants
GET /api/merchants/{merchant}
PUT /api/merchants/{merchant}
DELETE /api/merchants/{merchant}
GET /api/merchants/{merchant}/dashboard/summary
GET /api/merchants/{merchant}/finance/summary
GET /api/merchants/{merchant}/integration-tokens
POST /api/merchants/{merchant}/integration-tokens
GET /api/merchants/{merchant}/portal-users
POST /api/merchants/{merchant}/portal-users
GET /api/merchants/{merchant}/portal-users/{merchantUser}
PUT /api/merchants/{merchant}/portal-users/{merchantUser}
DELETE /api/merchants/{merchant}/portal-users/{merchantUser}
POST /api/merchants/{merchant}/portal-users/{merchantUser}/activate
POST /api/merchants/{merchant}/portal-users/{merchantUser}/suspend
GET /api/merchants/{merchant}/shop-preview
POST /api/merchants/{merchant}/wallet/deposit
GET /api/merchants/{merchant}/wallet/summary
GET /api/merchants/{merchant}/wallet/transactions
POST /api/merchants/{merchant}/wallet/withdraw
GET /api/merchants/{merchant}/warehouse/charges
GET /api/merchants/{merchant}/warehouse/inventory
GET /api/merchants/{merchant}/warehouse/movements
GET /api/orders
POST /api/orders
GET /api/orders/{order}
PUT /api/orders/{order}
DELETE /api/orders/{order}
POST /api/orders/{order}/confirm
POST /api/orders/{order}/fulfill-from-warehouse
GET /api/orders/{order}/label
POST /api/orders/{order}/release-stock
POST /api/orders/{order}/reserve-stock
POST /api/orders/{order}/shipments
GET /api/orders/{order}/stock-reservations
POST /api/orders/{order}/warehouse/mark-packed
POST /api/orders/{order}/warehouse/mark-picked
POST /api/orders/{order}/warehouse/ready-for-dispatch
POST /api/orders/{order}/warehouse/start-packing
POST /api/orders/{order}/warehouse/start-picking
GET /api/packing-tasks
POST /api/packing-tasks/generate
GET /api/packing-tasks/{packingTask}
POST /api/packing-tasks/{packingTask}/complete
GET /api/permissions
GET /api/permissions/grouped
GET /api/pick-pack-fee-rules
POST /api/pick-pack-fee-rules
GET /api/pick-pack-fee-rules/{pickPackFeeRule}
PUT /api/pick-pack-fee-rules/{pickPackFeeRule}
DELETE /api/pick-pack-fee-rules/{pickPackFeeRule}
POST /api/pick-pack-fees/generate
GET /api/picking-lists
POST /api/picking-lists/generate
GET /api/picking-lists/{pickingList}
POST /api/picking-lists/{pickingList}/cancel
POST /api/picking-lists/{pickingList}/scan-pick
GET /api/pricing-plan-rates
POST /api/pricing-plan-rates
GET /api/pricing-plan-rates/{pricingPlanRate}
PUT /api/pricing-plan-rates/{pricingPlanRate}
DELETE /api/pricing-plan-rates/{pricingPlanRate}
GET /api/pricing-plans
POST /api/pricing-plans
GET /api/pricing-plans/{pricingPlan}
PUT /api/pricing-plans/{pricingPlan}
DELETE /api/pricing-plans/{pricingPlan}
GET /api/roles
POST /api/roles
GET /api/roles/{role}
PUT /api/roles/{role}
DELETE /api/roles/{role}
POST /api/roles/{role}/permissions
GET /api/settings
PUT /api/settings/bulk-update
GET /api/settings/group/{group}
GET /api/settings/groups
GET /api/settings/{setting}
PUT /api/settings/{setting}
GET /api/shipments
POST /api/shipments/labels/bulk
GET /api/shipments/print-list
GET /api/shipments/{shipment}
POST /api/shipments/{shipment}/assign-driver
POST /api/shipments/{shipment}/complete-delivery
POST /api/shipments/{shipment}/delivery-attempt
GET /api/shipments/{shipment}/label
PATCH /api/shipments/{shipment}/status
GET /api/shipments/{shipment}/waybill
GET /api/shipping-rates
POST /api/shipping-rates
POST /api/shipping-rates/calculate
GET /api/shipping-rates/{shippingRate}
PUT /api/shipping-rates/{shippingRate}
DELETE /api/shipping-rates/{shippingRate}
DELETE /api/shipping-rates/{shippingRate}/force
POST /api/shipping-rates/{shippingRate}/restore
GET /api/stock-movements
GET /api/stock-movements/{stockMovement}
GET /api/stock-reservations
GET /api/storage-fee-rules
POST /api/storage-fee-rules
GET /api/storage-fee-rules/{storageFeeRule}
PUT /api/storage-fee-rules/{storageFeeRule}
DELETE /api/storage-fee-rules/{storageFeeRule}
POST /api/storage-fees/generate
GET /api/treasury-accounts
POST /api/treasury-accounts
GET /api/treasury-accounts/{treasuryAccount}
PUT /api/treasury-accounts/{treasuryAccount}
DELETE /api/treasury-accounts/{treasuryAccount}
GET /api/treasury-transactions
GET /api/user
GET /api/users
POST /api/users
GET /api/users/{user}
PUT /api/users/{user}
DELETE /api/users/{user}
POST /api/users/{user}/assign-role
GET /api/warehouse-bins
POST /api/warehouse-bins
GET /api/warehouse-bins/{warehouseBin}
PUT /api/warehouse-bins/{warehouseBin}
DELETE /api/warehouse-bins/{warehouseBin}
GET /api/warehouse-bins/{warehouseBin}/label
GET /api/warehouse-charges
POST /api/warehouse-charges
GET /api/warehouse-charges/{warehouseCharge}
PUT /api/warehouse-charges/{warehouseCharge}
DELETE /api/warehouse-charges/{warehouseCharge}
GET /api/warehouse-products
POST /api/warehouse-products
POST /api/warehouse-products/barcode-labels/bulk
POST /api/warehouse-products/labels/bulk
GET /api/warehouse-products/low-stock
GET /api/warehouse-products/{warehouseProduct}
PUT /api/warehouse-products/{warehouseProduct}
DELETE /api/warehouse-products/{warehouseProduct}
GET /api/warehouse-products/{warehouseProduct}/barcode
GET /api/warehouse-products/{warehouseProduct}/barcode-label
POST /api/warehouse-products/{warehouseProduct}/generate-barcode
GET /api/warehouse-products/{warehouseProduct}/label
GET /api/warehouse-receipts
POST /api/warehouse-receipts
GET /api/warehouse-receipts/{warehouseReceipt}
PUT /api/warehouse-receipts/{warehouseReceipt}
POST /api/warehouse-receipts/{warehouseReceipt}/cancel
POST /api/warehouse-receipts/{warehouseReceipt}/confirm
GET /api/warehouse-returns
POST /api/warehouse-returns
GET /api/warehouse-returns/{warehouseReturn}
POST /api/warehouse-returns/{warehouseReturn}/close
POST /api/warehouse-returns/{warehouseReturn}/inspect
POST /api/warehouse-returns/{warehouseReturn}/mark-damaged
POST /api/warehouse-returns/{warehouseReturn}/receive
POST /api/warehouse-returns/{warehouseReturn}/reject
POST /api/warehouse-returns/{warehouseReturn}/restock
PATCH /api/warehouse-returns/{warehouseReturn}/status
GET /api/warehouse-shelves
POST /api/warehouse-shelves
GET /api/warehouse-shelves/{warehouseShelf}
PUT /api/warehouse-shelves/{warehouseShelf}
DELETE /api/warehouse-shelves/{warehouseShelf}
GET /api/warehouse-transfers
POST /api/warehouse-transfers
GET /api/warehouse-transfers/{warehouseTransfer}
PUT /api/warehouse-transfers/{warehouseTransfer}
POST /api/warehouse-transfers/{warehouseTransfer}/cancel
POST /api/warehouse-transfers/{warehouseTransfer}/confirm
POST /api/warehouse-transfers/{warehouseTransfer}/dispatch
POST /api/warehouse-transfers/{warehouseTransfer}/receive
GET /api/warehouse-zones
POST /api/warehouse-zones
GET /api/warehouse-zones/{warehouseZone}
PUT /api/warehouse-zones/{warehouseZone}
DELETE /api/warehouse-zones/{warehouseZone}
POST /api/warehouse/barcode/scan
GET /api/warehouse/kpis
GET /api/warehouses
POST /api/warehouses
GET /api/warehouses/{warehouse}
PUT /api/warehouses/{warehouse}
DELETE /api/warehouses/{warehouse}
GET /api/waybill-batches
POST /api/waybill-batches
GET /api/waybill-batches/{batch}
GET /api/waybill-batches/{batch}/print
GET /api/waybill-batches/{batch}/simple-labels/print
POST /api/waybills/validate
GET /api/waybills/{waybill}/label
GET /api/waybills/{waybill}/simple-label
POST /api/waybills/{waybill}/void
```
