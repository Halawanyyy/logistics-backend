# Merchant Portal API Documentation

## 1. Overview

The Merchant Portal API is the merchant-facing layer of the Logistics & Warehouse backend.

It is built on top of the existing platform APIs, but every portal route is merchant-scoped and capability-checked.

Base prefix:

- `/api/merchant-portal`

## 2. Auth Method

- authentication uses the existing Sanctum login flow
- no separate merchant auth guard was added
- a successful login token can access merchant portal routes when the user has a valid merchant membership; non-active merchants are restricted to account/status endpoints

## 3. Merchant Portal Roles

- `owner`
- `admin`
- `staff`
- `accountant`
- `warehouse_viewer`

These roles are merchant-scoped. They do not replace the global internal admin/staff role system.

## 4. Capabilities

Common merchant portal capabilities:

- `dashboard.view`
- `profile.view`
- `profile.update`
- `users.view`
- `users.manage`
- `orders.view`
- `orders.create`
- `orders.update`
- `shipments.view`
- `warehouse.view`
- `warehouse.inbound_requests.view`
- `warehouse.inbound_requests.create`
- `warehouse.inbound_requests.cancel`
- `finance.view`
- `invoices.view`
- `invoices.download`
- `payouts.view`
- `payouts.request`
- `labels.print`
- `integrations.view`
- `integrations.manage`
- `exports.view`
- `exports.create`
- `exports.download`
- `attachments.view`
- `attachments.upload`
- `attachments.download`
- `attachments.delete`
- `notifications.view`

## 5. Security And Scoping Rules

- every `/api/merchant-portal/*` route requires `auth:sanctum`
- every `/api/merchant-portal/*` route requires merchant portal middleware from Phase 1
- merchant context comes only from the authenticated `merchant_users` membership
- `merchant_id` from query string or body is rejected with `422`
- cross-merchant resource access returns `404`
- internal-only finance, warehouse, and operational fields are not exposed

## 5A. Localization

- Merchant portal APIs support:
  - `X-Locale: ar|en`
  - `Accept-Language: ar|en`
- `X-Locale` overrides `Accept-Language` when both are sent.
- Raw role/status/type values remain unchanged.
- Merchant portal resources may include additive localized fields such as:
  - `status_label`
  - `role_label`
  - `portal_role_label`
  - `portal_status_label`
  - `type_label`
  - `direction_label`

## 6. Common Validation Errors

Common portal validation failures:

- `merchant_id` submitted in query or body
- invalid capability-restricted action for the current merchant role
- resource lookup outside the authenticated merchant scope
- invalid integration token abilities
- payout amount exceeding available balance
- attachment upload linked to another merchant's resource

## 7. Dashboard APIs

- `GET /api/merchant-portal/dashboard/summary`

Returns merchant-safe aggregates for:

- orders
- shipments
- warehouse
- finance
- latest orders
- latest shipments
- latest wallet transactions
- latest inbound requests

If the current role lacks `finance.view`, the finance section is `null`.

If the current role lacks `warehouse.view`, the warehouse section is `null`.

## 8. Profile And Settings APIs

Profile:

- `GET /api/merchant-portal/profile`
- `PUT /api/merchant-portal/profile`

Safe profile update fields:

- `name`
- `company_name`
- `phone`
- `email`
- `address`

Settings:

- `GET /api/merchant-portal/settings`
- `PUT /api/merchant-portal/settings`

Safe settings fields:

- `default_fulfillment_type`
- `default_payment_type`
- `default_pickup_address`
- `default_label_format`
- `notification_preferences`
- `integration_preferences`

## 9. Orders APIs

- `GET /api/merchant-portal/orders`
- `POST /api/merchant-portal/orders`
- `GET /api/merchant-portal/orders/{order}`
- `PUT /api/merchant-portal/orders/{order}`
- `POST /api/merchant-portal/orders/{order}/confirm`
- `POST /api/merchant-portal/orders/{order}/cancel`
- `GET /api/merchant-portal/orders/{order}/label`

Portal orders:

- are always merchant-scoped
- never accept `merchant_id`
- reuse the existing internal order creation, update, confirm, and label logic
- follow lifecycle safety rules before update or cancel

## 10. Shipments APIs

- `GET /api/merchant-portal/shipments`
- `GET /api/merchant-portal/shipments/{shipment}`
- `GET /api/merchant-portal/shipments/{shipment}/tracking`
- `GET /api/merchant-portal/shipments/{shipment}/label`
- `GET /api/merchant-portal/shipments/{shipment}/waybill`

Shipments are read-only in the merchant portal.

Merchant portal tracking remains merchant-scoped and can show merchant-safe shipment detail for the authenticated merchant's own shipments.
Public tracking at `GET /api/track/{shipment_number}` is more heavily redacted and does not expose customer name, full delivery address, or raw history notes.

## 11. Warehouse APIs

Products:

- `GET /api/merchant-portal/warehouse/products`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label`
- `POST /api/merchant-portal/warehouse/products/labels/bulk`

Inventory:

- `GET /api/merchant-portal/warehouse/inventory`
- `GET /api/merchant-portal/warehouse/inventory/low-stock`
- `GET /api/merchant-portal/warehouse/inventory/{inventoryStock}`

Movements and charges:

- `GET /api/merchant-portal/warehouse/movements`
- `GET /api/merchant-portal/warehouse/charges`

Inbound requests:

- `GET /api/merchant-portal/warehouse/inbound-requests`
- `POST /api/merchant-portal/warehouse/inbound-requests`
- `GET /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}`
- `POST /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}/cancel`

## 12. Inbound Stock Request APIs

Inbound requests are merchant-facing requests to send goods to the warehouse.

Important rule:

- creating an inbound request does not increase stock
- creating an inbound request does not create stock movement
- only internal warehouse receipt confirmation increases stock

## 13. Finance APIs

- `GET /api/merchant-portal/finance/summary`
- `GET /api/merchant-portal/finance/wallet/summary`
- `GET /api/merchant-portal/finance/wallet/transactions`
- `GET /api/merchant-portal/finance/wallet/transactions/{transaction}`
- `GET /api/merchant-portal/finance/invoices`
- `GET /api/merchant-portal/finance/invoices/{invoice}`
- `GET /api/merchant-portal/finance/invoices/{invoice}/preview`
- `GET /api/merchant-portal/finance/invoices/{invoice}/download`
- `GET /api/merchant-portal/finance/payouts`
- `GET /api/merchant-portal/finance/payouts/{payout}`
- `POST /api/merchant-portal/finance/payouts/request`

Merchant portal users can:

- view summary
- view wallet data
- view invoices
- preview/download invoices
- view payouts
- request payouts

Merchant portal users cannot:

- create or approve wallet entries
- create or edit invoices
- approve, reject, or complete payouts

## 14. Integration APIs

- `GET /api/merchant-portal/integration-tokens`
- `POST /api/merchant-portal/integration-tokens`
- `GET /api/merchant-portal/integration-tokens/{token}`
- `POST /api/merchant-portal/integration-tokens/{token}/revoke`
- `DELETE /api/merchant-portal/integration-tokens/{token}`
- `GET /api/merchant-portal/integrations/docs`
- `POST /api/merchant-portal/integrations/test`

Important rules:

- tokens belong only to the current merchant
- raw token is returned only once on create
- hashed token is never exposed
- only merchant-safe abilities are allowed
- `merchant_id` cannot be submitted in the payload because merchant scope always comes from the authenticated merchant portal membership

Allowed token abilities:

- `integrations.me`
- `orders.create`
- `orders.view`
- `shipments.view`
- `tracking.view`

## 15. Export APIs

- `GET /api/merchant-portal/exports/types`
- `POST /api/merchant-portal/exports`
- `GET /api/merchant-portal/exports`
- `GET /api/merchant-portal/exports/{export}`
- `GET /api/merchant-portal/exports/{export}/download`

Supported merchant export types:

- `orders`
- `shipments`
- `warehouse_inventory`
- `stock_movements`
- `wallet_transactions`
- `invoices`
- `payouts`
- `inbound_stock_requests`

Exports remain CSV-only in this implementation.

## 16. Attachment APIs

- `GET /api/merchant-portal/attachments`
- `POST /api/merchant-portal/attachments`
- `GET /api/merchant-portal/attachments/{attachment}`
- `GET /api/merchant-portal/attachments/{attachment}/download`
- `DELETE /api/merchant-portal/attachments/{attachment}`

Supported contexts:

- `merchant_profile`
- `order`
- `shipment`
- `invoice`
- `payout`
- `inbound_stock_request`
- `integration`
- `support_document`

Portal attachment responses do not expose internal storage paths.

## 17. Notification APIs

- `GET /api/merchant-portal/notifications`
- `GET /api/merchant-portal/notifications/unread-count`
- `POST /api/merchant-portal/notifications/{notification}/mark-read`
- `POST /api/merchant-portal/notifications/mark-all-read`

Notification types currently supported:

- `order_status_changed`
- `shipment_status_changed`
- `low_stock`
- `inbound_request_status_changed`
- `invoice_issued`
- `payout_status_changed`
- `integration_token_created`
- `integration_token_revoked`

## 18. Label Printing APIs

Merchant portal label endpoints:

- `GET /api/merchant-portal/orders/{order}/label`
- `GET /api/merchant-portal/shipments/{shipment}/label`
- `GET /api/merchant-portal/shipments/{shipment}/waybill`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label`
- `POST /api/merchant-portal/warehouse/products/labels/bulk`

Supported label formats:

- `html`
- `pdf`
- `zpl`
- `tspl`
- `json`

## 19. Example Payloads

Payout request:

```json
{
  "amount": 1000,
  "method": "bank_transfer",
  "bank_account_name": "ABC Store",
  "bank_account_number": "123456789",
  "bank_name": "Bank Name",
  "notes": "Please process this payout"
}
```

Integration token create:

```json
{
  "name": "WooCommerce Store",
  "expires_at": "2026-12-31",
  "abilities": ["orders.create", "orders.view"]
}
```

Export create:

```json
{
  "type": "orders",
  "filters": {
    "date_from": "2026-05-01",
    "date_to": "2026-05-31",
    "status": "delivered"
  }
}
```

## 20. Example Responses

Dashboard summary example:

```json
{
  "data": {
    "orders": {
      "total_orders": 120,
      "today_orders": 4,
      "pending_orders": 8
    },
    "shipments": {
      "total_shipments": 110,
      "in_transit_shipments": 9
    },
    "warehouse": {
      "warehouse_products_count": 35,
      "low_stock_count": 6
    },
    "finance": {
      "available_balance": "1520.00",
      "pending_payouts_count": 2
    }
  }
}
```

## 21. Frontend Notes

- never send `merchant_id` from the portal frontend
- rely on the authenticated merchant context from Phase 1
- expect `404` for cross-merchant lookups
- expect `403` when a role lacks the required capability
- use portal-safe resources instead of internal admin resources

## 22. Internal/Admin-Only Operations

The following remain internal/admin-only:

- approving or completing payouts
- creating ledger adjustments
- approving wallet transactions
- confirming warehouse receipts
- adjusting stock
- assigning drivers
- changing shipment operational statuses
- accessing internal profit and reconciliation views
- creating system-wide exports outside merchant scope

## 23. Self-Registration and Merchant Lifecycle

- Public sign-up: `POST /api/merchant-portal/register` (rate limited, no Sanctum token required).
- Registration creates the merchant, login user, and primary owner membership transactionally with merchant status `pending`.
- `/api/merchant-portal/me`, profile, settings, and notifications remain available for pending, rejected, and suspended merchants.
- All operational APIs require merchant status `active` and return lifecycle-specific `403` codes otherwise.
- `/api/merchant-portal/me` exposes registration/review status data, including the rejection reason when present.
- Admin decisions use explicit registration request endpoints; portal users cannot set merchant lifecycle status.
