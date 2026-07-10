# API Documentation

> This file must be updated whenever a new API endpoint is added or changed.

## Documentation Maintenance Rules

- Any new endpoint must be added here immediately.
- Any request validation change must be reflected here.
- Any response structure change must be reflected here.
- Keep examples simple and realistic.
- Group endpoints by module.

## Authentication Notes

- Protected endpoints use Sanctum bearer token authentication.
- Send header: `Authorization: Bearer YOUR_TOKEN`
- Many protected endpoints now also require a permission middleware check such as `permission:orders.view` or `permission:finance.payouts.create`.
- If the authenticated user does not have the required permission, the API returns `403`.

## Localization Notes

- Supported request locales:
  - `en`
  - `ar`
- Locale headers:
  - `X-Locale: ar` or `X-Locale: en`
  - `Accept-Language: ar` or `Accept-Language: en`
- Header priority:
  - `X-Locale`
  - `Accept-Language`
  - configured app default locale
- Locale support is additive:
  - stored enum/status values remain unchanged
  - API resources may now include additive localized fields such as `status_label`, `payment_type_label`, `fulfillment_type_label`, `role_label`, and `movement_type_label`
- Example:

```json
{
  "status": "delivered",
  "status_label": "تم التسليم"
}
```

Permission-denied response example:

```json
{
  "message": "You do not have permission to perform this action."
}
```

## Public Tracking Localization

- `GET /api/track/{shipment_number}` remains public, throttled, and redacted.
- It now localizes safe status/timeline labels using the active request locale.
- Raw shipment status values remain stable.
- Public tracking still does not expose:
  - customer full name
  - full delivery address
  - customer phone
  - driver/internal notes
  - raw history notes

## Warehouse Phase 2 APIs

### Fulfillment Type Compatibility

- Stored order values remain:
  - `pickup_from_merchant`
  - `from_warehouse`
  - `pickup_from_customer`
- The order create and update APIs now also accept these input aliases:
  - `merchant_pickup` -> stored as `pickup_from_merchant`
  - `warehouse_fulfillment` -> stored as `from_warehouse`
- Order responses now include additive field `warehouse_fulfillment_status`.

### Warehouse Receiving

- New endpoints:
  - `GET /api/warehouse-receipts`
  - `GET /api/warehouse-receipts/{warehouseReceipt}`
  - `POST /api/warehouse-receipts`
  - `PUT /api/warehouse-receipts/{warehouseReceipt}`
  - `POST /api/warehouse-receipts/{warehouseReceipt}/confirm`
  - `POST /api/warehouse-receipts/{warehouseReceipt}/cancel`
- Business rules:
  - Receipts are created as `draft`
  - Only draft receipts can be updated, confirmed, or cancelled
  - Confirming a receipt increases stock by `accepted_quantity` only
  - Confirmation creates immutable `stock_in` movements

Receipt request example:

```json
{
  "merchant_id": 1,
  "warehouse_id": 1,
  "notes": "Inbound merchant stock",
  "items": [
    {
      "warehouse_product_id": 1,
      "quantity": 10,
      "accepted_quantity": 9,
      "rejected_quantity": 1,
      "condition": "good",
      "notes": "One damaged carton"
    }
  ]
}
```

### Warehouse Product Barcode APIs

- New endpoints:
  - `POST /api/warehouse-products/{warehouseProduct}/generate-barcode`
  - `GET /api/warehouse-products/{warehouseProduct}/barcode`
  - `GET /api/warehouse-products/{warehouseProduct}/barcode-label`
  - `POST /api/warehouse-products/barcode-labels/bulk`
  - `POST /api/warehouse/barcode/scan`
- Notes:
  - Barcode generation is idempotent
  - Existing barcodes are preserved
  - Printable HTML is returned for label endpoints
  - Product responses now include additive fields:
    - `barcode_type`
    - `barcode_generated_at`
    - `minimum_stock_quantity`
    - `low_stock_alert_enabled`

Bulk label request example:

```json
{
  "items": [
    {
      "warehouse_product_id": 1,
      "quantity": 10
    }
  ]
}
```

Barcode scan request example:

```json
{
  "barcode": "WH-PROD-1-20260528-000001"
}
```

### Warehouse Order Workflow APIs

- New endpoints:
  - `POST /api/orders/{order}/warehouse/start-picking`
  - `POST /api/orders/{order}/warehouse/mark-picked`
  - `POST /api/orders/{order}/warehouse/start-packing`
  - `POST /api/orders/{order}/warehouse/mark-packed`
  - `POST /api/orders/{order}/warehouse/ready-for-dispatch`
- Existing compatible endpoints:
  - `POST /api/orders/{order}/reserve-stock`
  - `POST /api/orders/{order}/release-stock`
  - `POST /api/orders/{order}/fulfill-from-warehouse`
- `warehouse_fulfillment_status` values:
  - `not_required`
  - `pending_reservation`
  - `reserved`
  - `picking`
  - `picked`
  - `packing`
  - `packed`
  - `ready_for_dispatch`
  - `fulfilled`
  - `cancelled`

### Warehouse Return Lifecycle APIs

- Existing endpoint kept:
  - `POST /api/warehouse-returns`
- New endpoints:
  - `PATCH /api/warehouse-returns/{warehouseReturn}/status`
  - `POST /api/warehouse-returns/{warehouseReturn}/receive`
  - `POST /api/warehouse-returns/{warehouseReturn}/inspect`
  - `POST /api/warehouse-returns/{warehouseReturn}/restock`
  - `POST /api/warehouse-returns/{warehouseReturn}/mark-damaged`
  - `POST /api/warehouse-returns/{warehouseReturn}/reject`
  - `POST /api/warehouse-returns/{warehouseReturn}/close`
- Return statuses:
  - `pending`
  - `received`
  - `inspected`
  - `restocked`
  - `damaged`
  - `rejected`
  - `closed`
- Stock movement types used by returns:
  - `return_received`
  - `return_restocked`
  - `return_damaged`
  - `return_rejected`

Manual staged return create example:

```json
{
  "warehouse_id": 1,
  "notes": "Customer warehouse return",
  "items": [
    {
      "warehouse_product_id": 1,
      "expected_quantity": 2,
      "condition": "sellable"
    }
  ]
}
```

### Warehouse Transfer APIs

- New endpoints:
  - `GET /api/warehouse-transfers`
  - `GET /api/warehouse-transfers/{warehouseTransfer}`
  - `POST /api/warehouse-transfers`
  - `PUT /api/warehouse-transfers/{warehouseTransfer}`
  - `POST /api/warehouse-transfers/{warehouseTransfer}/confirm`
  - `POST /api/warehouse-transfers/{warehouseTransfer}/dispatch`
  - `POST /api/warehouse-transfers/{warehouseTransfer}/receive`
  - `POST /api/warehouse-transfers/{warehouseTransfer}/cancel`
- Transfer statuses:
  - `draft`
  - `confirmed`
  - `in_transit`
  - `received`
  - `cancelled`
- Stock movement types:
  - `stock_transfer_out`
  - `stock_transfer_in`

### Inventory, Movement, Product, Return, Receipt, and Transfer Filters

- `GET /api/inventory-stocks`
  - `merchant_id`, `warehouse_id`, `warehouse_product_id`, `warehouse_bin_id`, `sku`, `barcode`, `low_stock`, `has_reserved`, `available_only`
- `GET /api/inventory-stocks/low-stock`
- `GET /api/stock-movements`
  - `merchant_id`, `warehouse_id`, `warehouse_product_id`, `type`, `movement_type`, `reference_type`, `reference_id`, `order_id`, `date_from`, `date_to`
- `GET /api/warehouse-products`
  - `merchant_id`, `warehouse_id`, `sku`, `barcode`, `search`, `status`, `low_stock`
- `GET /api/warehouse-products/low-stock`
- `GET /api/warehouse-returns`
  - `merchant_id`, `warehouse_id`, `order_id`, `shipment_id`, `warehouse_product_id`, `condition`, `status`, `date_from`, `date_to`
- `GET /api/warehouse-receipts`
  - `merchant_id`, `warehouse_id`, `status`, `receipt_number`, `date_from`, `date_to`
- `GET /api/warehouse-transfers`
  - `source_warehouse_id`, `destination_warehouse_id`, `status`, `transfer_number`, `date_from`, `date_to`

### Stock Movement Types

- Existing:
  - `in`
  - `out`
  - `reserved`
  - `released`
  - `damaged`
  - `adjustment`
- Added:
  - `stock_in`
  - `return_received`
  - `return_restocked`
  - `return_damaged`
  - `return_rejected`
  - `stock_transfer_out`
  - `stock_transfer_in`

### Warehouse Complete Flow

1. Create warehouse
2. Create warehouse product
3. Generate barcode
4. Receive merchant stock
5. Confirm receipt
6. View inventory
7. Create warehouse fulfillment order
8. Reserve stock
9. Pick
10. Pack
11. Ready for dispatch
12. Fulfill from warehouse
13. Shipment flow continues
14. Return if needed
15. Inspect return
16. Restock, damage, or reject
17. Review the stock movement audit trail

## Auth APIs

### POST `/api/auth/login`

- Method: `POST`
- Authentication: `No`
- Purpose: Authenticates an internal user and returns a Sanctum token.
- Rate limit: `throttle:auth-login` with the default limit sourced from `RATE_LIMIT_AUTH_LOGIN_PER_MINUTE`.

Request body example:

```json
{
  "email": "admin@example.com",
  "password": "secret123"
}
```

Success response example:

```json
{
  "message": "Login successful",
  "token": "1|example-sanctum-token",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "email_verified_at": null,
    "created_at": "2026-04-24T10:00:00.000000Z",
    "updated_at": "2026-04-24T10:00:00.000000Z",
    "deleted_at": null
  }
}
```

Common error response example:

```json
{
  "message": "Invalid credentials"
}
```

### GET `/api/auth/me`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns the currently authenticated user.

Success response example:

```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "email_verified_at": null,
    "created_at": "2026-04-24T10:00:00.000000Z",
    "updated_at": "2026-04-24T10:00:00.000000Z",
    "deleted_at": null
  }
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/auth/logout`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Deletes the current Sanctum access token and logs the user out.

Success response example:

```json
{
  "message": "Logged out successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### GET `/api/auth/ping`

- Method: `GET`
- Authentication: `No`
- Purpose: Simple health check for the Auth module.

Success response example:

```json
{
  "message": "Auth module is working"
}
```

Common error response example:

```json
{
  "message": "Server error"
}
```

## User Management APIs

### GET `/api/users`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all internal users ordered by newest first.

Success response example:

```json
{
  "data": [
    {
      "id": 2,
      "name": "Warehouse Manager",
      "email": "warehouse.manager@example.com",
      "role": "warehouse",
      "role_id": 4,
      "role_name": "Warehouse",
      "role_slug": "warehouse",
      "permissions": [
        {
          "id": 41,
          "module": "Warehouse",
          "name": "View Inventory",
          "slug": "inventory.view",
          "description": "View inventory."
        }
      ],
      "created_at": "2026-04-24T11:00:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/users`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a new internal company user.

Request body example:

```json
{
  "name": "Finance Officer",
  "email": "finance@example.com",
  "password": "secret123",
  "role": "finance"
}
```

Success response example:

```json
{
  "data": {
    "id": 3,
    "name": "Finance Officer",
    "email": "finance@example.com",
    "role": "finance",
    "role_id": 5,
    "role_name": "Finance",
    "role_slug": "finance",
    "permissions": [
      {
        "id": 52,
        "module": "Finance",
        "name": "View Finance Summary",
        "slug": "finance.view",
        "description": "View finance summaries."
      }
    ],
    "created_at": "2026-04-24T11:05:00.000000Z"
  }
}
```

## Orders And Shipments Final Integration Notes

### Order lifecycle guardrails

- Orders can still be created and confirmed through the existing APIs.
- Orders cannot be edited or deleted after shipment creation.
- Warehouse-fulfilled orders also become locked once warehouse processing starts, including stock reservation and later warehouse workflow stages.
- `fulfillment_type` stored values remain:
  - `pickup_from_merchant`
  - `from_warehouse`
  - `pickup_from_customer`
- Input aliases remain accepted:
  - `merchant_pickup`
  - `warehouse_fulfillment`

### Shipment creation safety

- `POST /api/orders/{order}/shipments` remains the shipment creation endpoint.
- Merchant-pickup orders can create shipments after confirmation.
- Warehouse-fulfilled orders can create shipments only after `warehouse_fulfillment_status = fulfilled`.
- Duplicate shipment creation is still blocked by both application validation and the unique `shipments.order_id` constraint.

### Shipment status updates

- `PATCH /api/shipments/{shipment}/status` is now limited to operational lifecycle updates.
- Use `POST /api/shipments/{shipment}/complete-delivery` for:
  - `delivered`
  - `partially_delivered`
  - `failed`
- Warehouse-fulfilled return outcomes should be completed through item delivery results so the warehouse return lifecycle can continue safely.

### Driver and manifest integration

- `POST /api/shipments/{shipment}/assign-driver` keeps the same route and now moves `pending_pickup` shipments to `driver_assigned`.
- Driver manifest generation keeps shipment assignment aligned with the selected driver and prevents loading a shipment already assigned to another driver.

### Finance and warehouse return integration

- Shipment delivery completion remains the integration point for:
  - order item delivery results
  - merchant wallet COD and fee transactions
  - shipment status history
- Returned warehouse items now create staged warehouse returns first.
- Stock does not silently increase from shipment returns until the warehouse return is received, inspected, and restocked through the warehouse lifecycle.

### Verified additive filters

Orders index supports:

- `date_from`
- `date_to`
- `merchant_id`
- `merchant_name`
- `status`
- `delivery_status`
- `order_type`
- `fulfillment_type`
- `payment_type`
- `delivery_governorate_id`
- `delivery_area_id`
- `customer_phone`
- `order_number`
- `has_shipment`
- `warehouse_fulfillment_status`
- `search`

Shipments index supports:

- `date_from`
- `date_to`
- `merchant_id`
- `driver_id` or `assigned_driver_id`
- `status`
- `shipment_number`
- `search`
- `delivery_governorate_id` or `governorate_id`
- `delivery_area_id` or `area_id`
- `payment_type`
- `cod_only`

Shipment print list supports:

- `date`
- `delivery_date` as an input alias to `date`
- `assigned_driver_id`
- `merchant_id`
- `status`
- `delivery_governorate_id`
- `delivery_area_id`

## Settings APIs

### Settings Concept

- `system_settings` provides centralized reusable configuration across company, finance, shipping, warehouse, barcode, notification, security, system, and integration behavior.
- Settings are grouped, typed, sortable, and include `label`, `description`, `options`, and `validation_rules` so the frontend can render settings forms dynamically.
- Encrypted settings are stored encrypted and masked in API responses.
- Public settings are exposed separately and only include rows where `is_public = true`.
- Non-editable settings cannot be changed through API.
- Existing services keep fallback defaults when a setting is missing.

### GET `/api/settings`

- Method: `GET`
- Authentication: `Yes`
- Permission: `settings.view`
- Purpose: Returns paginated system settings.
- Optional filters:
- `group`
- `key`
- `is_public`
- `is_editable`
- `search`
- `per_page`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "group": "finance",
      "key": "default_currency",
      "value": "EGP",
      "type": "string",
      "label": "Default Currency",
      "description": "System-wide default currency code.",
      "is_public": false,
      "is_encrypted": false,
      "is_editable": true,
      "options": ["EGP", "USD", "SAR"],
      "validation_rules": {
        "rules": "required|in:EGP,USD,SAR"
      },
      "sort_order": 10,
      "updated_by_name": "Admin User",
      "updated_at": "2026-05-23T10:00:00.000000Z"
    }
  ]
}
```

### GET `/api/settings/groups`

- Method: `GET`
- Authentication: `Yes`
- Permission: `settings.groups`
- Purpose: Returns settings groups with counts.

Success response example:

```json
{
  "data": [
    {
      "group": "finance",
      "settings_count": 8
    },
    {
      "group": "shipping",
      "settings_count": 6
    }
  ]
}
```

### GET `/api/settings/group/{group}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `settings.view`
- Purpose: Returns all settings inside the selected group.

### GET `/api/settings/{setting}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `settings.view`
- Purpose: Returns one setting.

Encrypted setting response behavior:

- `value` is returned as `"********"` for encrypted settings.

### PUT `/api/settings/{setting}`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `settings.update`
- Purpose: Updates one editable setting.

Request body example:

```json
{
  "value": "Fast Logistics"
}
```

Rules:

- Non-editable settings return `422`
- Value is validated by type
- Value is validated by `validation_rules.rules` when configured
- Encrypted settings are stored encrypted
- Audit event `setting_updated` is created

### PUT `/api/settings/bulk-update`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `settings.bulk_update`
- Purpose: Updates multiple settings inside one transaction.

Request body example:

```json
{
  "settings": [
    {
      "key": "company_name",
      "value": "Fast Logistics"
    },
    {
      "key": "default_currency",
      "value": "EGP"
    }
  ]
}
```

Rules:

- Unknown keys return `422`
- Non-editable settings return `422`
- All values are validated before commit
- Audit event `settings_bulk_updated` is created

### GET `/api/public/settings`

- Method: `GET`
- Authentication: `No`
- Purpose: Returns only public settings.

Success response example:

```json
{
  "data": [
    {
      "group": "company",
      "key": "company_name",
      "value": "Fast Logistics",
      "type": "string",
      "label": "Company Name",
      "description": "Primary company display name.",
      "options": null,
      "sort_order": 1
    }
  ]
}
```

### Seeded Default Settings

- Company:
- `company_name`
- `company_phone`
- `company_email`
- `company_address`
- `company_tax_number`
- `company_logo`
- `company_website`

- Finance:
- `default_currency`
- `payout_approval_limit`
- `expense_approval_limit`
- `auto_approve_system_transactions`
- `invoice_prefix`
- `invoice_due_days`
- `wallet_manual_transactions_require_approval`
- `treasury_default_cash_account_code`

- Shipping:
- `default_order_status`
- `default_shipment_status`
- `max_delivery_attempts`
- `allow_partial_delivery`
- `allow_order_edit_after_shipment`
- `allow_order_delete_after_shipment`

- Warehouse:
- `low_stock_threshold`
- `default_return_condition`
- `storage_fee_generate_day`
- `auto_generate_pick_pack_fees`
- `auto_restore_sellable_returns`

- Barcode:
- `order_barcode_prefix`
- `shipment_barcode_prefix`
- `warehouse_product_barcode_prefix`
- `warehouse_bin_barcode_prefix`
- `barcode_date_format`

- Notifications:
- `email_enabled`
- `sms_enabled`
- `whatsapp_enabled`
- `notify_customer_on_shipment_created`
- `notify_customer_on_status_change`
- `notify_merchant_on_wallet_transaction`
- `notify_merchant_on_invoice_issued`
- `shipment_created_template`
- `shipment_delivered_template`
- `shipment_failed_template`
- `wallet_deposit_template`
- `wallet_withdraw_template`
- `invoice_issued_template`

- System:
- `timezone`
- `date_format`
- `datetime_format`
- `pagination_limit`
- `maintenance_mode`
- `support_email`
- `support_phone`

- Security:
- `password_min_length`
- `session_timeout_minutes`
- `force_password_change_days`
- `login_audit_enabled`

- Integrations:
- `whatsapp_provider`
- `sms_provider`
- `email_provider`
- `payment_gateway`

### Existing Services Now Using Settings

- Barcode generation uses configurable prefixes and barcode date format.
- Order creation uses `default_order_status`.
- Shipment creation uses `default_shipment_status`.
- Delivery attempts respect `max_delivery_attempts`.
- Partial delivery flow respects `allow_partial_delivery`.
- Order update/delete after shipment respects `allow_order_edit_after_shipment` and `allow_order_delete_after_shipment`.
- Wallet manual transactions use `wallet_manual_transactions_require_approval`.
- Payout auto-approval can use `payout_approval_limit`.
- Expense auto-approval can use `expense_approval_limit`.
- Finance summaries and invoice resources expose `default_currency`.
- Invoice numbering uses `invoice_prefix`.
- Invoice responses compute `due_date` from `invoice_due_days`.
- Warehouse returns use `default_return_condition` and `auto_restore_sellable_returns`.
- Warehouse KPI summary uses `low_stock_threshold`.
- Paginated endpoints can use `pagination_limit` as their default size.

Common error response example:

```json
{
  "message": "The selected role is invalid.",
  "errors": {
    "role": [
      "The selected role is invalid."
    ]
  }
}
```

### GET `/api/users/{user}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one internal user by ID.

Success response example:

```json
{
  "data": {
    "id": 3,
    "name": "Finance Officer",
    "email": "finance@example.com",
    "role": "finance",
    "role_id": 5,
    "role_name": "Finance",
    "role_slug": "finance",
    "permissions": [
      {
        "id": 52,
        "module": "Finance",
        "name": "View Finance Summary",
        "slug": "finance.view",
        "description": "View finance summaries."
      }
    ],
    "created_at": "2026-04-24T11:05:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Models\\User] 999"
}
```

### PUT `/api/users/{user}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an existing internal user. Password is re-hashed if provided.

Request body example:

```json
{
  "name": "Updated Finance Officer",
  "email": "finance.updated@example.com",
  "password": "newsecret123",
  "role": "admin"
}
```

Success response example:

```json
{
  "data": {
    "id": 3,
    "name": "Updated Finance Officer",
    "email": "finance.updated@example.com",
    "role": "admin",
    "role_id": 2,
    "role_name": "Admin",
    "role_slug": "admin",
    "permissions": [
      {
        "id": 23,
        "module": "Orders",
        "name": "View Orders",
        "slug": "orders.view",
        "description": "View orders."
      }
    ],
    "created_at": "2026-04-24T11:05:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

### DELETE `/api/users/{user}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes an internal user.

Success response example:

```json
{
  "message": "User deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

## Roles & Permissions APIs

### Permission Middleware Notes

- Protected routes can now require both Sanctum auth and a specific permission.
- Examples:
- `GET /api/orders` requires `orders.view`
- `POST /api/orders/{order}/shipments` requires `orders.create_shipment`
- `PATCH /api/shipments/{shipment}/status` requires `shipments.update_status`
- `GET /api/merchant-payouts` requires `finance.payouts.view`
- `POST /api/inventory-stocks/adjust` requires `inventory.adjust`
- Public tracking stays public and redacted: `GET /api/track/{shipment_number}`

### GET `/api/roles`

- Method: `GET`
- Authentication: `Yes`
- Permission: `roles.view`
- Purpose: Returns all roles with grouped permissions.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Super Admin",
      "slug": "super_admin",
      "description": "Full system access across all modules and permissions.",
      "status": "active",
      "is_system": true,
      "permissions": {
        "Orders": [
          {
            "id": 23,
            "module": "Orders",
            "name": "View Orders",
            "slug": "orders.view",
            "description": "View orders."
          }
        ],
        "Finance": [
          {
            "id": 52,
            "module": "Finance",
            "name": "View Finance Summary",
            "slug": "finance.view",
            "description": "View finance summaries."
          }
        ]
      },
      "created_at": "2026-05-22T10:00:00.000000Z"
    }
  ]
}
```

### POST `/api/roles`

- Method: `POST`
- Authentication: `Yes`
- Permission: `roles.create`
- Purpose: Creates a custom role and optionally assigns permissions.

Request body example:

```json
{
  "name": "Operations Supervisor",
  "slug": "operations_supervisor",
  "description": "Can manage orders, shipments, and drivers.",
  "status": "active",
  "permissions": [23, 24, 27, 29, 31, 35]
}
```

Success response example:

```json
{
  "data": {
    "id": 7,
    "name": "Operations Supervisor",
    "slug": "operations_supervisor",
    "description": "Can manage orders, shipments, and drivers.",
    "status": "active",
    "is_system": false,
    "permissions": {
      "Orders": [
        {
          "id": 23,
          "module": "Orders",
          "name": "View Orders",
          "slug": "orders.view",
          "description": "View orders."
        }
      ],
      "Shipments": [
        {
          "id": 29,
          "module": "Shipments",
          "name": "View Shipments",
          "slug": "shipments.view",
          "description": "View shipments."
        }
      ]
    },
    "created_at": "2026-05-22T10:10:00.000000Z"
  }
}
```

### GET `/api/roles/{role}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `roles.view`
- Purpose: Returns one role with grouped permissions.

### PUT `/api/roles/{role}`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `roles.update`
- Purpose: Updates a role. System role slugs stay locked.

Request body example:

```json
{
  "name": "Operations Lead",
  "description": "Updated role description",
  "status": "active"
}
```

### DELETE `/api/roles/{role}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `roles.delete`
- Purpose: Deletes a non-system role that is not assigned to users.

Success response example:

```json
{
  "message": "Role deleted successfully"
}
```

### POST `/api/roles/{role}/permissions`

- Method: `POST`
- Authentication: `Yes`
- Permission: `permissions.assign`
- Purpose: Syncs the permissions assigned to a role. `super_admin` always keeps all permissions.

Request body example:

```json
{
  "permissions": [23, 24, 27, 29, 31, 35]
}
```

### GET `/api/permissions`

- Method: `GET`
- Authentication: `Yes`
- Permission: `permissions.view`
- Purpose: Returns all permissions as a flat list.

Success response example:

```json
{
  "data": [
    {
      "id": 23,
      "module": "Orders",
      "name": "View Orders",
      "slug": "orders.view",
      "description": "View orders."
    }
  ]
}
```

### GET `/api/permissions/grouped`

- Method: `GET`
- Authentication: `Yes`
- Permission: `permissions.view`
- Purpose: Returns permissions grouped by module.

Success response example:

```json
{
  "data": [
    {
      "module": "Orders",
      "permissions": [
        {
          "id": 23,
          "module": "Orders",
          "name": "View Orders",
          "slug": "orders.view",
          "description": "View orders."
        }
      ]
    }
  ]
}
```

### POST `/api/users/{user}/assign-role`

- Method: `POST`
- Authentication: `Yes`
- Permission: `users.update` and `roles.update`
- Purpose: Assigns a role to a user and updates `role_id`. When the role slug matches a legacy role, the old `role` string is kept in sync too.

Request body example:

```json
{
  "role_id": 2
}
```

Success response example:

```json
{
  "data": {
    "id": 3,
    "name": "Updated Finance Officer",
    "email": "finance.updated@example.com",
    "role": "admin",
    "role_id": 2,
    "role_name": "Admin",
    "role_slug": "admin",
    "permissions": [
      {
        "id": 23,
        "module": "Orders",
        "name": "View Orders",
        "slug": "orders.view",
        "description": "View orders."
      }
    ],
    "created_at": "2026-04-24T11:05:00.000000Z"
  }
}
```

### Permission List Grouped By Module

- User Management: `users.view`, `users.create`, `users.update`, `users.delete`
- Roles & Permissions: `roles.view`, `roles.create`, `roles.update`, `roles.delete`, `permissions.view`, `permissions.assign`
- Audit: `audit_logs.view`, `audit_logs.timeline`
- Settings: `settings.view`, `settings.update`, `settings.public_view`, `settings.groups`, `settings.bulk_update`
- Exports: `exports.view`, `exports.create`, `exports.download`
- Attachments: `attachments.view`, `attachments.upload`, `attachments.download`, `attachments.delete`
- Merchant Integrations: `merchant_integrations.view`, `merchant_integrations.create`, `merchant_integrations.revoke`, `merchant_integrations.delete`
- Barcodes: `barcodes.lookup`, `barcodes.scan`
- Merchants: `merchants.view`, `merchants.create`, `merchants.update`, `merchants.delete`
- Locations & Pricing: `locations.view`, `locations.create`, `locations.update`, `locations.delete`, `shipping_rates.view`, `shipping_rates.create`, `shipping_rates.update`, `shipping_rates.delete`, `pricing_plans.view`, `pricing_plans.create`, `pricing_plans.update`, `pricing_plans.delete`, `pricing_plan_rates.view`, `pricing_plan_rates.create`, `pricing_plan_rates.update`, `pricing_plan_rates.delete`
- Orders: `orders.view`, `orders.create`, `orders.update`, `orders.delete`, `orders.confirm`, `orders.create_shipment`
- Shipments: `shipments.view`, `shipments.create`, `shipments.update_status`, `shipments.assign_driver`, `shipments.print`, `shipments.return_to_warehouse`
- Drivers: `drivers.view`, `drivers.create`, `drivers.update`, `drivers.delete`, `drivers.manifest`, `drivers.cash_closure`, `driver_manifests.view`, `driver_manifests.create`, `driver_manifests.close`, `driver_manifests.print`, `delivery_attempts.view`, `delivery_attempts.create`
- Warehouse: `warehouses.view`, `warehouses.create`, `warehouses.update`, `warehouses.delete`, `warehouse_products.view`, `warehouse_products.create`, `warehouse_products.update`, `warehouse_products.delete`, `inventory.view`, `inventory.adjust`, `inventory.reserve`, `inventory.release`, `inventory.fulfill`, `warehouse_returns.view`, `warehouse_returns.create`, `warehouse_charges.view`, `warehouse_charges.create`, `warehouse_charges.update`, `warehouse_charges.delete`, `warehouse_locations.view`, `warehouse_locations.create`, `warehouse_locations.update`, `warehouse_locations.delete`, `picking_lists.view`, `picking_lists.create`, `picking_lists.update`, `picking_lists.cancel`, `picking_lists.scan`, `packing_tasks.view`, `packing_tasks.create`, `packing_tasks.complete`, `storage_fee_rules.view`, `storage_fee_rules.create`, `storage_fee_rules.update`, `storage_fee_rules.delete`, `storage_fees.generate`, `pick_pack_fee_rules.view`, `pick_pack_fee_rules.create`, `pick_pack_fee_rules.update`, `pick_pack_fee_rules.delete`, `pick_pack_fees.generate`, `warehouse_kpis.view`
- Finance: `finance.view`, `merchant_wallet.view`, `merchant_wallet.deposit`, `merchant_wallet.withdraw`, `merchant_wallet.approve`, `merchant_wallet.reject`, `finance.payouts.view`, `finance.payouts.create`, `finance.payouts.update`, `finance.payouts.approve`, `finance.payouts.reject`, `finance.payouts.complete`, `finance.expenses.view`, `finance.expenses.create`, `finance.expenses.update`, `finance.expenses.approve`, `finance.expenses.reject`, `finance.expenses.pay`, `finance.invoices.view`, `finance.invoices.create`, `finance.invoices.download`, `finance.reports.view`, `finance.reports.daily_cash`, `finance.reports.merchant_ledger`, `finance.reports.driver_discrepancies`, `finance.reports.warehouse_revenue`, `finance.reports.profit_loss`, `finance.treasury.view`, `finance.treasury.create`, `finance.treasury.update`, `finance.treasury.delete`, `finance.reconciliation.view`
- Dashboard: `dashboard.view`, `dashboard.merchant_view`

## Merchant APIs

### GET `/api/merchants`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all merchants ordered by newest first.
- Optional filters:
- `pricing_plan_id`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Ahmed Ali",
      "company_name": "Fast Cargo LLC",
      "phone": "+201001234567",
      "email": "merchant@example.com",
      "address": "Cairo, Nasr City",
      "status": "active",
      "pricing_plan_id": 2,
      "pricing_plan_name": "Growth",
      "pricing_plan_code": "growth",
      "created_at": "2026-04-24T12:00:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/merchants`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a new merchant record.

Request body example:

```json
{
  "name": "Ahmed Ali",
  "company_name": "Fast Cargo LLC",
  "phone": "+201001234567",
  "email": "merchant@example.com",
  "address": "Cairo, Nasr City",
  "status": "active",
  "pricing_plan_id": 2
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "name": "Ahmed Ali",
    "company_name": "Fast Cargo LLC",
    "phone": "+201001234567",
    "email": "merchant@example.com",
    "address": "Cairo, Nasr City",
    "status": "active",
    "pricing_plan_id": 2,
    "pricing_plan_name": "Growth",
    "pricing_plan_code": "growth",
    "created_at": "2026-04-24T12:00:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The selected status is invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

### GET `/api/merchants/{merchant}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one merchant by ID.

Success response example:

```json
{
  "data": {
    "id": 1,
    "name": "Ahmed Ali",
    "company_name": "Fast Cargo LLC",
    "phone": "+201001234567",
    "email": "merchant@example.com",
    "address": "Cairo, Nasr City",
    "status": "active",
    "pricing_plan_id": 2,
    "pricing_plan_name": "Growth",
    "pricing_plan_code": "growth",
    "created_at": "2026-04-24T12:00:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Models\\Merchant] 999"
}
```

### PUT `/api/merchants/{merchant}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an existing merchant record.

Request body example:

```json
{
  "pricing_plan_id": 3,
  "company_name": "Fast Cargo Logistics",
  "status": "inactive"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "name": "Ahmed Ali",
    "company_name": "Fast Cargo Logistics",
    "phone": "+201001234567",
    "email": "merchant@example.com",
    "address": "Cairo, Nasr City",
    "status": "inactive",
    "pricing_plan_id": 3,
    "pricing_plan_name": "Enterprise",
    "pricing_plan_code": "enterprise",
    "created_at": "2026-04-24T12:00:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

### DELETE `/api/merchants/{merchant}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a merchant.

Success response example:

```json
{
  "message": "Merchant deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

## Location Pricing APIs

### GET `/api/governorates`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all governorates ordered by newest first.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Cairo",
      "is_active": true,
      "created_at": "2026-04-24T13:00:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/governorates`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a new governorate for shipping coverage and pricing setup.

Request body example:

```json
{
  "name": "Giza",
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 2,
    "name": "Giza",
    "is_active": true,
    "created_at": "2026-04-24T13:05:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The name has already been taken.",
  "errors": {
    "name": [
      "The name has already been taken."
    ]
  }
}
```

### GET `/api/governorates/{governorate}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one governorate by ID.

Success response example:

```json
{
  "data": {
    "id": 2,
    "name": "Giza",
    "is_active": true,
    "created_at": "2026-04-24T13:05:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Modules\\LocationPricing\\Models\\Governorate] 999"
}
```

### PUT `/api/governorates/{governorate}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates a governorate record.

Request body example:

```json
{
  "name": "Greater Cairo",
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 2,
    "name": "Greater Cairo",
    "is_active": true,
    "created_at": "2026-04-24T13:05:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The name has already been taken.",
  "errors": {
    "name": [
      "The name has already been taken."
    ]
  }
}
```

### DELETE `/api/governorates/{governorate}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a governorate.

Success response example:

```json
{
  "message": "Governorate deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### GET `/api/areas`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all areas with their related governorate name.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "governorate_id": 2,
      "governorate_name": "Greater Cairo",
      "name": "Nasr City",
      "is_active": true,
      "created_at": "2026-04-24T13:10:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/areas`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates an area inside a governorate.

Request body example:

```json
{
  "governorate_id": 2,
  "name": "Nasr City",
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "name": "Nasr City",
    "is_active": true,
    "created_at": "2026-04-24T13:10:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The name has already been taken for this governorate.",
  "errors": {
    "name": [
      "The name has already been taken for this governorate."
    ]
  }
}
```

### GET `/api/areas/{area}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one area by ID.

Success response example:

```json
{
  "data": {
    "id": 1,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "name": "Nasr City",
    "is_active": true,
    "created_at": "2026-04-24T13:10:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Modules\\LocationPricing\\Models\\Area] 999"
}
```

### PUT `/api/areas/{area}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an area and keeps area names unique inside the same governorate.

Request body example:

```json
{
  "name": "New Nasr City",
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "name": "New Nasr City",
    "is_active": true,
    "created_at": "2026-04-24T13:10:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The selected governorate id is invalid.",
  "errors": {
    "governorate_id": [
      "The selected governorate id is invalid."
    ]
  }
}
```

### DELETE `/api/areas/{area}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes an area.

Success response example:

```json
{
  "message": "Area deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### GET `/api/shipping-rates`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all shipping rates with governorate and area names when available.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "governorate_id": 2,
      "governorate_name": "Greater Cairo",
      "area_id": null,
      "area_name": null,
      "shipping_fee": "60.00",
      "is_active": true,
      "created_at": "2026-04-24T13:20:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/shipping-rates`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a shipping rate for a full governorate or a specific area.

Request body example:

```json
{
  "governorate_id": 2,
  "area_id": 1,
  "shipping_fee": 80,
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 2,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "area_id": 1,
    "area_name": "New Nasr City",
    "shipping_fee": "80.00",
    "is_active": true,
    "created_at": "2026-04-24T13:25:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "The selected area does not belong to the selected governorate.",
  "errors": {
    "area_id": [
      "The selected area does not belong to the selected governorate."
    ]
  }
}
```

### GET `/api/shipping-rates/{shippingRate}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one shipping rate by ID.

Success response example:

```json
{
  "data": {
    "id": 2,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "area_id": 1,
    "area_name": "New Nasr City",
    "shipping_fee": "80.00",
    "is_active": true,
    "created_at": "2026-04-24T13:25:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Modules\\LocationPricing\\Models\\ShippingRate] 999"
}
```

### PUT `/api/shipping-rates/{shippingRate}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an existing shipping rate.

Request body example:

```json
{
  "shipping_fee": 90,
  "is_active": true
}
```

Success response example:

```json
{
  "data": {
    "id": 2,
    "governorate_id": 2,
    "governorate_name": "Greater Cairo",
    "area_id": 1,
    "area_name": "New Nasr City",
    "shipping_fee": "90.00",
    "is_active": true,
    "created_at": "2026-04-24T13:25:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "A shipping rate already exists for this location.",
  "errors": {
    "area_id": [
      "A shipping rate already exists for this location."
    ]
  }
}
```

### DELETE `/api/shipping-rates/{shippingRate}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a shipping rate.

Success response example:

```json
{
  "message": "Shipping rate deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/shipping-rates/calculate`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Calculates the shipping fee for a location. If `merchant_id` is provided and the merchant has an active pricing plan, the system checks pricing plan rates first. It then falls back to the legacy `shipping_rates` table.

Request body example:

```json
{
  "merchant_id": 1,
  "order_type": "swap",
  "governorate_id": 2,
  "area_id": 1
}
```

Success response example:

```json
{
  "data": {
    "merchant_id": 1,
    "order_type": "swap",
    "governorate_id": 2,
    "area_id": 1,
    "shipping_fee": "80.00",
    "source": "pricing_plan_area"
  }
}
```

Common error response example:

```json
{
  "message": "Shipping rate not configured."
}
```

### Merchant Pricing Plan Logic

- If a merchant has an active pricing plan, shipping fee resolution checks that plan first.
- Priority order:
- exact area rate inside the pricing plan
- governorate default rate inside the pricing plan where `area_id = null`
- fallback to legacy `shipping_rates`
- Fee field by order type:
- `delivery` -> `delivery_fee`
- `swap` -> `swap_fee`
- `return` -> `return_fee`
- Order creation stores a `shipping_fee` snapshot on the order, so later pricing-plan changes do not affect old orders.

Example plan comparison:

- Starter plan Cairo delivery: `100.00`
- Growth plan Cairo delivery: `80.00`
- Enterprise plan Cairo delivery: `65.00`

## Pricing Plan APIs

### GET `/api/pricing-plans`

- Method: `GET`
- Authentication: `Yes`
- Permission: `pricing_plans.view`
- Purpose: Returns all pricing plans ordered by newest first.
- Optional filters:
- `status`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Growth",
      "code": "growth",
      "description": "Preferred pricing for growing merchants.",
      "monthly_orders_from": 101,
      "monthly_orders_to": 500,
      "status": "active",
      "merchants_count": 12,
      "created_at": "2026-05-23T10:00:00.000000Z"
    }
  ]
}
```

### POST `/api/pricing-plans`

- Method: `POST`
- Authentication: `Yes`
- Permission: `pricing_plans.create`
- Purpose: Creates a merchant pricing plan.

Request body example:

```json
{
  "name": "Enterprise",
  "code": "enterprise",
  "description": "High-volume merchant plan.",
  "monthly_orders_from": 501,
  "monthly_orders_to": null,
  "status": "active"
}
```

### GET `/api/pricing-plans/{pricingPlan}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `pricing_plans.view`
- Purpose: Returns one pricing plan.

### PUT `/api/pricing-plans/{pricingPlan}`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `pricing_plans.update`
- Purpose: Updates a pricing plan.

### DELETE `/api/pricing-plans/{pricingPlan}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `pricing_plans.delete`
- Purpose: Soft deletes a pricing plan that is not currently assigned to merchants.

Success response example:

```json
{
  "message": "Pricing plan deleted successfully"
}
```

### GET `/api/pricing-plan-rates`

- Method: `GET`
- Authentication: `Yes`
- Permission: `pricing_plan_rates.view`
- Purpose: Returns pricing plan rates with optional filters.
- Optional filters:
- `pricing_plan_id`
- `governorate_id`
- `area_id`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "pricing_plan_id": 2,
      "pricing_plan_name": "Growth",
      "governorate_id": 1,
      "governorate_name": "Cairo",
      "area_id": null,
      "area_name": null,
      "delivery_fee": "80.00",
      "swap_fee": "95.00",
      "return_fee": "60.00",
      "is_active": true
    }
  ]
}
```

### POST `/api/pricing-plan-rates`

- Method: `POST`
- Authentication: `Yes`
- Permission: `pricing_plan_rates.create`
- Purpose: Creates a pricing plan rate for a governorate default or a specific area.

Request body example:

```json
{
  "pricing_plan_id": 2,
  "governorate_id": 1,
  "area_id": 5,
  "delivery_fee": 75,
  "swap_fee": 90,
  "return_fee": 55,
  "is_active": true
}
```

### GET `/api/pricing-plan-rates/{pricingPlanRate}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `pricing_plan_rates.view`
- Purpose: Returns one pricing plan rate.

### PUT `/api/pricing-plan-rates/{pricingPlanRate}`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `pricing_plan_rates.update`
- Purpose: Updates a pricing plan rate.

### DELETE `/api/pricing-plan-rates/{pricingPlanRate}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `pricing_plan_rates.delete`
- Purpose: Deletes a pricing plan rate.

Success response example:

```json
{
  "message": "Pricing plan rate deleted successfully"
}
```

## Order APIs

### Order Rules

- `order_type` supports `delivery`, `swap`, and `return`.
- `delivery` is the normal shipment flow.
- `swap` means the shipment delivers a new item, collects the old item, and may collect a COD difference.
- `return` means the driver only picks up returned items from the customer.
- `delivery_governorate_id` is required for every order.
- `delivery_area_id` is optional.
- `payment_type` supports `cod` and `prepaid`.
- `cod` means the customer pays on delivery.
- `prepaid` means the customer already paid online.
- `fulfillment_type` supports `pickup_from_merchant`, `from_warehouse`, and `pickup_from_customer`.
- Return orders should use `pickup_from_customer`.
- Swap orders may optionally reference `original_order_id` and may include `swap_notes`.
- If `shipping_fee` is not sent on create, the system first checks the merchant's active pricing plan by location and `order_type`.
- Pricing plan lookup priority:
- exact area rate inside the plan
- governorate default rate inside the plan where `area_id` is `null`
- fallback to the legacy `shipping_rates` table
- If no pricing plan rate or fallback shipping rate is found, order creation returns `422` with message `Shipping rate not configured.`
- Order numbers are generated automatically in this format: `ORD-YYYYMMDD-000001`.

### Order Type Finance Examples

- `delivery`: `cod_amount` is the amount collected from the customer and `shipping_fee` is the delivery fee.
- `swap`: `cod_amount` is the difference collected from the customer and `shipping_fee` is the swap service fee.
- `return`: `cod_amount` is usually `0.00` and `shipping_fee` is the return fee charged to the merchant.
- `delivered_cod_amount` is calculated from delivered item quantities only.
- Prepaid orders can still have delivered items, but COD and driver cash calculations only use orders where `payment_type = cod`.

Swap example:

- Old order value: `1000`
- New order value: `1500`
- COD difference: `500`
- Shipping fee: `80`
- Customer pays on visit: `580`

### Partial Delivery Rules

- Every order item now tracks:
- `delivery_status`
- `delivered_quantity`
- `returned_quantity`
- `damaged_quantity`
- Allowed item delivery statuses:
- `pending`
- `reserved`
- `fulfilled`
- `partially_delivered`
- `delivered`
- `returned`
- `damaged`
- `cancelled`
- Quantity rule: `delivered_quantity + returned_quantity + damaged_quantity` cannot exceed the original item `quantity`.
- Shipment COD collection is based on delivered items only, not the original full order `cod_amount`.

### GET `/api/orders`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all orders ordered by newest first with merchant, delivery location, and items eager loaded.
- Optional filters:
- `status`
- `order_type`
- `fulfillment_type`
- `payment_type`
- `delivery_status`
- `partially_delivered`
- `merchant_id`
- `merchant_name`
- `date_from`
- `date_to`
- `search`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "merchant_id": 1,
      "merchant_name": "Fast Trade",
      "order_number": "ORD-20260424-000001",
      "customer_name": "Ahmed Adel",
      "customer_phone": "01000000000",
      "customer_phone_alt": null,
      "delivery_governorate_id": 2,
      "delivery_governorate_name": "Cairo",
      "delivery_area_id": 5,
      "delivery_area_name": "Nasr City",
      "delivery_address": "Building 10, Street 12",
      "delivery_notes": "Call before delivery",
      "pickup_governorate_id": null,
      "pickup_area_id": null,
      "pickup_address": null,
      "pickup_notes": null,
      "cod_amount": "450.00",
      "shipping_fee": "80.00",
      "fulfillment_type": "pickup_from_merchant",
      "is_fragile": false,
      "allow_inspection": true,
      "requires_packaging": false,
      "package_notes": null,
      "source": "manual",
      "external_source": null,
      "external_order_id": null,
      "external_order_number": null,
      "requires_review": false,
      "review_reason": null,
      "status": "draft",
      "notes": "Priority customer",
      "items": [
        {
          "id": 1,
          "product_name": "Phone Case",
          "sku": "CASE-001",
          "quantity": 2,
          "unit_price": "150.00",
          "weight": "0.20",
          "notes": null,
          "created_at": "2026-04-24T14:00:00.000000Z"
        }
      ],
      "created_at": "2026-04-24T14:00:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/orders`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a manual order with one or more items inside a database transaction.

Request body example:

```json
{
  "merchant_id": 1,
  "order_type": "delivery",
  "customer_name": "Ahmed Adel",
  "customer_phone": "01000000000",
  "customer_phone_alt": "01111111111",
  "delivery_governorate_id": 2,
  "delivery_area_id": 5,
  "delivery_address": "Building 10, Street 12, Cairo",
  "delivery_notes": "Call before delivery",
  "payment_type": "cod",
  "cod_amount": 450,
  "fulfillment_type": "pickup_from_merchant",
  "allow_inspection": true,
  "notes": "Priority customer",
  "items": [
    {
      "product_name": "Phone Case",
      "sku": "CASE-001",
      "quantity": 2,
      "unit_price": 150,
      "weight": 0.2
    },
    {
      "product_name": "Screen Protector",
      "quantity": 1,
      "unit_price": 150
    }
  ]
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "order_number": "ORD-20260424-000001",
    "order_type": "delivery",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "customer_phone_alt": "01111111111",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "delivery_notes": "Call before delivery",
    "pickup_governorate_id": null,
    "pickup_area_id": null,
    "pickup_address": null,
    "pickup_notes": null,
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "original_order_id": null,
    "original_order_number": null,
    "swap_notes": null,
    "payment_type": "cod",
    "fulfillment_type": "pickup_from_merchant",
    "is_fragile": false,
    "allow_inspection": true,
    "requires_packaging": false,
    "package_notes": null,
    "source": "manual",
    "external_source": null,
    "external_order_id": null,
    "external_order_number": null,
    "requires_review": false,
    "review_reason": null,
    "status": "draft",
    "notes": "Priority customer",
    "items": [
      {
        "id": 1,
        "product_name": "Phone Case",
        "sku": "CASE-001",
        "quantity": 2,
        "unit_price": "150.00",
        "weight": "0.20",
        "notes": null,
        "created_at": "2026-04-24T14:00:00.000000Z"
      },
      {
        "id": 2,
        "product_name": "Screen Protector",
        "sku": null,
        "quantity": 1,
        "unit_price": "150.00",
        "weight": null,
        "notes": null,
        "created_at": "2026-04-24T14:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T14:00:00.000000Z"
  }
}
```

Shipping-rate-not-configured error response example:

```json
{
  "message": "Shipping rate not configured."
}
```

Common validation error response example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "delivery_governorate_id": [
      "The delivery governorate id field is required."
    ]
  }
}
```

### GET `/api/orders/{order}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one order by ID with merchant, delivery location, and items eager loaded.

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "order_number": "ORD-20260424-000001",
    "order_type": "delivery",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "customer_phone_alt": "01111111111",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "delivery_notes": "Call before delivery",
    "pickup_governorate_id": null,
    "pickup_area_id": null,
    "pickup_address": null,
    "pickup_notes": null,
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "original_order_id": null,
    "original_order_number": null,
    "swap_notes": null,
    "fulfillment_type": "pickup_from_merchant",
    "is_fragile": false,
    "allow_inspection": true,
    "requires_packaging": false,
    "package_notes": null,
    "source": "manual",
    "external_source": null,
    "external_order_id": null,
    "external_order_number": null,
    "requires_review": false,
    "review_reason": null,
    "status": "draft",
    "notes": "Priority customer",
    "items": [
      {
        "id": 1,
        "product_name": "Phone Case",
        "sku": "CASE-001",
        "quantity": 2,
        "unit_price": "150.00",
        "weight": "0.20",
        "notes": null,
        "created_at": "2026-04-24T14:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T14:00:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Modules\\Order\\Models\\Order] 999"
}
```

### Partial Delivery Example Order Item Shape

```json
{
  "id": 12,
  "product_name": "Phone Case",
  "sku": "CASE-001",
  "quantity": 3,
  "delivery_status": "partially_delivered",
  "delivered_quantity": 2,
  "returned_quantity": 1,
  "damaged_quantity": 0,
  "unit_price": "150.00",
  "weight": "0.20",
  "notes": null,
  "created_at": "2026-04-24T14:00:00.000000Z"
}
```

### POST `/api/orders/{order}/confirm`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Confirms an order after admin/system review. This endpoint only changes the order status to `confirmed` when confirmation rules pass.

Confirmation rules:

- Order can be confirmed only when current status is `draft` or `pending_review`.
- Order cannot be confirmed when `requires_review = true`.
- Order cannot be confirmed when it has no items.
- Already confirmed orders return `422`.
- Cancelled orders return `422`.

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "order_number": "ORD-20260424-000001",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "customer_phone_alt": "01111111111",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "delivery_notes": "Call before delivery",
    "pickup_governorate_id": null,
    "pickup_area_id": null,
    "pickup_address": null,
    "pickup_notes": null,
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "fulfillment_type": "pickup_from_merchant",
    "is_fragile": false,
    "allow_inspection": true,
    "requires_packaging": false,
    "package_notes": null,
    "source": "manual",
    "external_source": null,
    "external_order_id": null,
    "external_order_number": null,
    "requires_review": false,
    "review_reason": null,
    "status": "confirmed",
    "notes": "Priority customer",
    "items": [
      {
        "id": 1,
        "product_name": "Phone Case",
        "sku": "CASE-001",
        "quantity": 2,
        "unit_price": "150.00",
        "weight": "0.20",
        "notes": null,
        "created_at": "2026-04-24T14:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T14:00:00.000000Z"
  }
}
```

Already confirmed error response example:

```json
{
  "message": "Order is already confirmed."
}
```

Cancelled order error response example:

```json
{
  "message": "Cancelled order cannot be confirmed."
}
```

Review-required error response example:

```json
{
  "message": "Order requires review before confirmation."
}
```

No items error response example:

```json
{
  "message": "Order must have at least one item before confirmation."
}
```

### PUT `/api/orders/{order}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an order inside a database transaction. If `items` is sent, all existing order items are replaced with the new array.

Request body example:

```json
{
  "order_type": "swap",
  "original_order_id": 1,
  "swap_notes": "Collect old phone case from customer during exchange",
  "customer_name": "Ahmed Adel Updated",
  "delivery_governorate_id": 2,
  "delivery_area_id": 6,
  "delivery_address": "Updated address",
  "shipping_fee": 95,
  "status": "confirmed",
  "items": [
    {
      "product_name": "Phone Case Premium",
      "sku": "CASE-002",
      "quantity": 1,
      "unit_price": 300,
      "weight": 0.25
    }
  ]
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "order_number": "ORD-20260424-000001",
    "order_type": "swap",
    "customer_name": "Ahmed Adel Updated",
    "customer_phone": "01000000000",
    "customer_phone_alt": "01111111111",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 6,
    "delivery_area_name": "Heliopolis",
    "delivery_address": "Updated address",
    "delivery_notes": "Call before delivery",
    "pickup_governorate_id": null,
    "pickup_area_id": null,
    "pickup_address": null,
    "pickup_notes": null,
    "cod_amount": "450.00",
    "shipping_fee": "95.00",
    "original_order_id": 1,
    "original_order_number": "ORD-20260424-000001",
    "swap_notes": "Collect old phone case from customer during exchange",
    "fulfillment_type": "pickup_from_merchant",
    "is_fragile": false,
    "allow_inspection": true,
    "requires_packaging": false,
    "package_notes": null,
    "source": "manual",
    "external_source": null,
    "external_order_id": null,
    "external_order_number": null,
    "requires_review": false,
    "review_reason": null,
    "status": "confirmed",
    "notes": "Priority customer",
    "items": [
      {
        "id": 4,
        "product_name": "Phone Case Premium",
        "sku": "CASE-002",
        "quantity": 1,
        "unit_price": "300.00",
        "weight": "0.25",
        "notes": null,
        "created_at": "2026-04-24T14:20:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T14:00:00.000000Z"
  }
}
```

Common validation error response example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "delivery_area_id": [
      "The selected delivery area does not belong to the selected delivery governorate."
    ]
  }
}
```

### DELETE `/api/orders/{order}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes an order.

Success response example:

```json
{
  "message": "Order deleted successfully"
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

## Shipment APIs

### Shipment Rules

- Shipment is the operational logistics entity created from a confirmed order.
- One order can have only one shipment for now.
- Shipment creation copies key delivery and financial fields from the order.
- Every shipment starts with status `pending_pickup`.
- Every shipment status change creates a row in `shipment_status_histories`.
- Shipment resources now include `order_type` and `fulfillment_type` from the linked order.
- Shipment resources now also include:
- `delivered_cod_amount`
- `item_results`
- `delivery` shipments follow the normal delivery lifecycle.
- `swap` shipments deliver new products, collect returned products, and may collect a COD difference.
- `return` shipments only pick up returned products and usually end in `returned_to_hub` or `returned_to_warehouse`.
- Partial delivery results are completed item-by-item through `POST /api/shipments/{shipment}/complete-delivery`.
- Returned sellable items from `from_warehouse` shipments create warehouse returns automatically and restore `inventory.quantity_available`.
- Returned damaged items from `from_warehouse` shipments create warehouse returns automatically and increase `inventory.quantity_damaged`.
- Returned disposed items create warehouse returns and use stock movement type `adjustment`.
- Damaged items with a linked warehouse product increase damaged inventory and create a damaged stock movement.

### Shipment Status Meanings

- `pending_pickup`: shipment created and waiting for operational pickup.
- `driver_assigned`: driver assigned but pickup not completed yet.
- `picked_up`: shipment picked up from merchant, warehouse, or customer.
- `arrived_at_hub`: shipment arrived at the company hub.
- `sorting`: shipment is being sorted operationally.
- `ready_for_dispatch`: shipment is ready for last-mile dispatch.
- `in_transit`: legacy general transport status kept for backward compatibility.
- `out_for_delivery`: shipment is with the driver for delivery.
- `delivery_attempted`: delivery was attempted but not completed.
- `rescheduled`: shipment is rescheduled for another visit.
- `delivered`: shipment successfully delivered.
- `partially_delivered`: reserved for future Phase 2 support.
- `failed`: shipment failed operationally.
- `returned`: legacy return-complete status kept for backward compatibility.
- `returned_to_hub`: return shipment collected and back at hub.
- `returned_to_warehouse`: return shipment completed back to warehouse/company.
- `cancelled`: shipment was cancelled.

### GET `/api/track/{shipment_number}`

- Method: `GET`
- Authentication: `No`
- Purpose: Public customer tracking endpoint using shipment number. This endpoint does not require Sanctum authentication.
- Rate limit: `throttle:public-tracking` with the default limit sourced from `RATE_LIMIT_PUBLIC_TRACKING_PER_MINUTE`.
- Response is intentionally limited to customer-safe shipment tracking data.
- This route does not expose customer name, customer phone, full delivery address, merchant identifiers, driver data, finance data, warehouse data, or raw/internal notes.
- `tracking_events` is the preferred timeline field. `histories` remains as a backward-compatible safe alias with the same redacted event shape.

Success response example:

```json
{
  "data": {
    "shipment_number": "SHP-20260424-000001",
    "status": "in_transit",
    "current_status": "in_transit",
    "status_label": "In Transit",
    "delivery_governorate_name": "Cairo",
    "delivery_area_name": "Nasr City",
    "latest_tracking_event": {
      "status": "in_transit",
      "current_status": "in_transit",
      "status_label": "In Transit",
      "created_at": "2026-04-24T15:30:00.000000Z"
    },
    "tracking_events": [
      {
        "status": "pending_pickup",
        "current_status": "pending_pickup",
        "status_label": "Pending Pickup",
        "created_at": "2026-04-24T15:00:00.000000Z"
      },
      {
        "status": "in_transit",
        "current_status": "in_transit",
        "status_label": "In Transit",
        "created_at": "2026-04-24T15:30:00.000000Z"
      }
    ],
    "histories": [
      {
        "status": "pending_pickup",
        "current_status": "pending_pickup",
        "status_label": "Pending Pickup",
        "created_at": "2026-04-24T15:00:00.000000Z"
      },
      {
        "status": "in_transit",
        "current_status": "in_transit",
        "status_label": "In Transit",
        "created_at": "2026-04-24T15:30:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z",
    "updated_at": "2026-04-24T15:30:00.000000Z"
  }
}
```

Not found response example:

```json
{
  "message": "Shipment not found."
}
```

### GET `/api/shipments`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all shipments ordered by newest first with merchant, delivery location, and status histories.
- Optional filters:
- `status`
- `order_type`
- `partially_delivered`
- `merchant_id`
- `assigned_driver_id`
- `delivery_governorate_id`
- `delivery_area_id`
- `date_from`
- `date_to`
- `search` on `shipment_number`, `customer_name`, or `customer_phone`

Request examples:

```text
GET /api/shipments?status=in_transit
GET /api/shipments?merchant_id=1&assigned_driver_id=1
GET /api/shipments?delivery_governorate_id=1&delivery_area_id=2
GET /api/shipments?date_from=2026-04-01&date_to=2026-04-24
GET /api/shipments?search=010
```

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "shipment_number": "SHP-20260424-000001",
      "merchant_id": 1,
      "merchant_name": "Fast Trade",
      "customer_name": "Ahmed Adel",
      "customer_phone": "01000000000",
      "delivery_governorate_id": 2,
      "delivery_governorate_name": "Cairo",
      "delivery_area_id": 5,
      "delivery_area_name": "Nasr City",
      "delivery_address": "Building 10, Street 12, Cairo",
      "cod_amount": "450.00",
      "shipping_fee": "80.00",
      "order_type": "delivery",
      "fulfillment_type": "pickup_from_merchant",
      "status": "pending_pickup",
      "tracking_notes": null,
      "histories": [
        {
          "id": 1,
          "shipment_id": 1,
          "status": "pending_pickup",
          "notes": "Shipment created from order.",
          "changed_by": 1,
          "changed_by_name": "Admin User",
          "created_at": "2026-04-24T15:00:00.000000Z"
        }
      ],
      "created_at": "2026-04-24T15:00:00.000000Z"
    }
  ]
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

### POST `/api/shipments/{shipment}/complete-delivery`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Completes shipment delivery at item level, creates shipment item results, updates order item delivery counters, updates shipment and order statuses, and restores warehouse stock when applicable.

Request body example:

```json
{
  "shipment_status": "partially_delivered",
  "notes": "Customer accepted some items only",
  "items": [
    {
      "order_item_id": 12,
      "result_status": "delivered",
      "quantity": 2
    },
    {
      "order_item_id": 13,
      "warehouse_product_id": 1,
      "result_status": "returned",
      "quantity": 1,
      "return_condition": "sellable",
      "notes": "Customer refused item"
    }
  ]
}
```

Business behavior:

- `delivered` item result:
- Creates a `shipment_item_results` row
- Increases `order_items.delivered_quantity`
- Updates the order item `delivery_status`
- Contributes to `delivered_cod_amount`
- `returned` item result:
- Creates a `shipment_item_results` row
- Increases `order_items.returned_quantity`
- If `warehouse_product_id` exists and the order fulfillment type is `from_warehouse`, a warehouse return is created automatically
- `damaged` item result:
- Creates a `shipment_item_results` row
- Increases `order_items.damaged_quantity`
- If `warehouse_product_id` is linked to warehouse flow, damaged inventory is increased
- `cancelled` item result:
- Creates a `shipment_item_results` row
- Does not contribute to delivered COD

Return condition behavior:

- `sellable`: inventory available increases and stock movement type is `in`
- `damaged`: inventory damaged increases and stock movement type is `damaged`
- `disposed`: no available stock is restored and stock movement type is `adjustment`

Shipment status resolution after item processing:

- All delivered -> `delivered`
- Mix of delivered and non-delivered -> `partially_delivered`
- All returned, damaged, or cancelled -> `failed`

Order status resolution after shipment completion:

- Shipment `delivered` -> order `delivered`
- Shipment `partially_delivered` -> order `partially_delivered`
- Shipment `failed` -> order `failed`

Success response example:

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "shipment_number": "SHP-20260424-000001",
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "delivered_cod_amount": "300.00",
    "order_type": "swap",
    "fulfillment_type": "from_warehouse",
    "status": "partially_delivered",
    "tracking_notes": null,
    "item_results": [
      {
        "id": 1,
        "shipment_id": 1,
        "order_item_id": 12,
        "product_name": "Phone Case",
        "result_status": "delivered",
        "quantity": 2,
        "return_condition": null,
        "notes": null,
        "created_at": "2026-04-24T15:40:00.000000Z"
      },
      {
        "id": 2,
        "shipment_id": 1,
        "order_item_id": 13,
        "product_name": "Old Phone Case",
        "result_status": "returned",
        "quantity": 1,
        "return_condition": "sellable",
        "notes": "Customer refused item",
        "created_at": "2026-04-24T15:40:00.000000Z"
      }
    ],
    "histories": [
      {
        "id": 1,
        "shipment_id": 1,
        "status": "pending_pickup",
        "notes": "Shipment created from order.",
        "changed_by": 1,
        "changed_by_name": "Admin User",
        "created_at": "2026-04-24T15:00:00.000000Z"
      },
      {
        "id": 2,
        "shipment_id": 1,
        "status": "partially_delivered",
        "notes": "Customer accepted some items only",
        "changed_by": 2,
        "changed_by_name": "Operations User",
        "created_at": "2026-04-24T15:40:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z"
  }
}
```

Common validation error response example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items.0.quantity": [
      "The quantity cannot exceed the remaining quantity for this item."
    ]
  }
}
```

### GET `/api/shipments/{shipment}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one shipment by ID with merchant, delivery location, and status history.

Success response example:

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "shipment_number": "SHP-20260424-000001",
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "order_type": "delivery",
    "fulfillment_type": "pickup_from_merchant",
    "status": "pending_pickup",
    "tracking_notes": null,
    "histories": [
      {
        "id": 1,
        "shipment_id": 1,
        "status": "pending_pickup",
        "notes": "Shipment created from order.",
        "changed_by": 1,
        "changed_by_name": "Admin User",
        "created_at": "2026-04-24T15:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z"
  }
}
```

Common error response example:

```json
{
  "message": "No query results for model [App\\Modules\\Shipment\\Models\\Shipment] 999"
}
```

### POST `/api/orders/{order}/shipments`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Converts a confirmed order into a shipment and creates the first shipment status history row.

Creation rules:

- Order must be confirmed.
- Order must not require review.
- Order must have at least one item.
- Order must not already have a shipment.

Success response example:

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "shipment_number": "SHP-20260424-000001",
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "order_type": "delivery",
    "fulfillment_type": "pickup_from_merchant",
    "status": "pending_pickup",
    "tracking_notes": null,
    "histories": [
      {
        "id": 1,
        "shipment_id": 1,
        "status": "pending_pickup",
        "notes": "Shipment created from order.",
        "changed_by": 1,
        "changed_by_name": "Admin User",
        "created_at": "2026-04-24T15:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z"
  }
}
```

Order not confirmed error response example:

```json
{
  "message": "Only confirmed orders can be converted to shipment."
}
```

Review-required error response example:

```json
{
  "message": "Order requires review before shipment creation."
}
```

Shipment already exists error response example:

```json
{
  "message": "Shipment already exists for this order."
}
```

No items error response example:

```json
{
  "message": "Order must have at least one item before shipment creation."
}
```

### PATCH `/api/shipments/{shipment}/status`

- Method: `PATCH`
- Authentication: `Yes`
- Purpose: Updates the current shipment status and adds a new shipment status history record.

Request body example:

```json
{
  "status": "in_transit",
  "notes": "Shipment left the sorting hub."
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "shipment_number": "SHP-20260424-000001",
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "order_type": "delivery",
    "fulfillment_type": "pickup_from_merchant",
    "status": "in_transit",
    "tracking_notes": null,
    "histories": [
      {
        "id": 1,
        "shipment_id": 1,
        "status": "pending_pickup",
        "notes": "Shipment created from order.",
        "changed_by": 1,
        "changed_by_name": "Admin User",
        "created_at": "2026-04-24T15:00:00.000000Z"
      },
      {
        "id": 2,
        "shipment_id": 1,
        "status": "in_transit",
        "notes": "Shipment left the sorting hub.",
        "changed_by": 2,
        "changed_by_name": "Operations User",
        "created_at": "2026-04-24T15:30:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z"
  }
}
```

Common validation error response example:

```json
{
  "message": "The selected status is invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

### POST `/api/shipments/{shipment}/assign-driver`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Assigns or reassigns an active driver to a shipment without changing shipment status.

Request body example:

```json
{
  "driver_id": 1
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "order_id": 1,
    "shipment_number": "SHP-20260424-000001",
    "merchant_id": 1,
    "merchant_name": "Fast Trade",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 2,
    "delivery_governorate_name": "Cairo",
    "delivery_area_id": 5,
    "delivery_area_name": "Nasr City",
    "delivery_address": "Building 10, Street 12, Cairo",
    "cod_amount": "450.00",
    "shipping_fee": "80.00",
    "status": "pending_pickup",
    "tracking_notes": null,
    "assigned_driver_id": 1,
    "assigned_driver_name": "Mahmoud Hassan",
    "histories": [
      {
        "id": 1,
        "shipment_id": 1,
        "status": "pending_pickup",
        "notes": "Shipment created from order.",
        "changed_by": 1,
        "changed_by_name": "Admin User",
        "created_at": "2026-04-24T15:00:00.000000Z"
      }
    ],
    "created_at": "2026-04-24T15:00:00.000000Z"
  }
}
```

Inactive driver error response example:

```json
{
  "message": "Selected driver is inactive."
}
```

Delivered shipment error response example:

```json
{
  "message": "Delivered shipment cannot be reassigned."
}
```

Cancelled shipment error response example:

```json
{
  "message": "Cancelled shipment cannot be assigned."
}
```

### GET `/api/delivery-attempts`

- Method: `GET`
- Authentication: `Yes`
- Permission: `delivery_attempts.view`
- Purpose: Returns shipment delivery attempts with optional filters.
- Optional filters:
- `shipment_id`
- `driver_id`
- `result`
- `reason`
- `date_from`
- `date_to`

### POST `/api/shipments/{shipment}/delivery-attempt`

- Method: `POST`
- Authentication: `Yes`
- Permission: `delivery_attempts.create`
- Purpose: Records a driver delivery attempt, increments the shipment attempt number, updates shipment status, and creates shipment history.

Request body example:

```json
{
  "result": "failed",
  "reason": "customer_not_answering",
  "notes": "Customer phone unreachable",
  "next_action": "retry_tomorrow",
  "next_attempt_date": "2026-05-24 10:00:00"
}
```

Status behavior:

- `failed` result sets shipment status to `failed`
- `postponed` or `rescheduled` sets shipment status to `rescheduled`
- `customer_unreachable` or `customer_refused` sets shipment status to `delivery_attempted`

Success response example:

```json
{
  "data": {
    "id": 1,
    "shipment_id": 1,
    "shipment_number": "SHP-20260523-000001",
    "shipment_status": "delivery_attempted",
    "driver_id": 1,
    "driver_name": "Mahmoud Hassan",
    "attempt_number": 1,
    "attempt_date": "2026-05-23T15:30:00.000000Z",
    "result": "failed",
    "reason": "customer_not_answering",
    "notes": "Customer phone unreachable",
    "next_action": "retry_tomorrow",
    "next_attempt_date": "2026-05-24T10:00:00.000000Z",
    "created_by": 2,
    "created_by_name": "Operations User",
    "created_at": "2026-05-23T15:30:00.000000Z"
  }
}
```

## Driver APIs

### Driver Rules

- Driver is a separate operational entity and is not a system user by default.
- `user_id` is optional for future linking with an internal user account.
- Only active drivers can be assigned to shipments.
- Daily operations are now managed through `driver_manifests`.
- One driver can have only one open manifest for the same date.
- Shipments loaded into a manifest are moved to `out_for_delivery`.
- Manifest closing records expected COD, actual received cash, and discrepancy values for finance reconciliation.

### GET `/api/drivers`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all drivers ordered by newest first.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "user_id": null,
      "name": "Mahmoud Hassan",
      "phone": "01012345678",
      "national_id": "29801011234567",
      "vehicle_type": "Motorcycle",
      "vehicle_plate": "ABC-1234",
      "status": "active",
      "notes": "Primary Cairo driver",
      "created_at": "2026-04-24T16:00:00.000000Z"
    }
  ]
}
```

### POST `/api/drivers`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a driver record.

Request body example:

```json
{
  "name": "Mahmoud Hassan",
  "phone": "01012345678",
  "national_id": "29801011234567",
  "vehicle_type": "Motorcycle",
  "vehicle_plate": "ABC-1234",
  "status": "active",
  "notes": "Primary Cairo driver"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "user_id": null,
    "name": "Mahmoud Hassan",
    "phone": "01012345678",
    "national_id": "29801011234567",
    "vehicle_type": "Motorcycle",
    "vehicle_plate": "ABC-1234",
    "status": "active",
    "notes": "Primary Cairo driver",
    "created_at": "2026-04-24T16:00:00.000000Z"
  }
}
```

### GET `/api/drivers/{driver}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one driver by ID.

Success response example:

```json
{
  "data": {
    "id": 1,
    "user_id": null,
    "name": "Mahmoud Hassan",
    "phone": "01012345678",
    "national_id": "29801011234567",
    "vehicle_type": "Motorcycle",
    "vehicle_plate": "ABC-1234",
    "status": "active",
    "notes": "Primary Cairo driver",
    "created_at": "2026-04-24T16:00:00.000000Z"
  }
}
```

### PUT `/api/drivers/{driver}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates a driver record.

Request body example:

```json
{
  "vehicle_type": "Van",
  "vehicle_plate": "XYZ-5678",
  "status": "inactive"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "user_id": null,
    "name": "Mahmoud Hassan",
    "phone": "01012345678",
    "national_id": "29801011234567",
    "vehicle_type": "Van",
    "vehicle_plate": "XYZ-5678",
    "status": "inactive",
    "notes": "Primary Cairo driver",
    "created_at": "2026-04-24T16:00:00.000000Z"
  }
}
```

### DELETE `/api/drivers/{driver}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a driver.

Success response example:

```json
{
  "message": "Driver deleted successfully"
}
```

## Driver Manifest APIs

### Driver Manifest Lifecycle

- Operations staff generate a manifest for one driver and one manifest date.
- Manifest items are shipment snapshots captured at loadout time.
- Shipments cannot belong to multiple open manifests.
- Manifest expected COD is recalculated at close time from delivered or partially delivered shipments.
- Manifest closing automatically creates or updates a linked `driver_cash_closure`.
- A manifest cannot be closed while one of its shipments is still unresolved operationally.

### POST `/api/driver-manifests/generate`

- Method: `POST`
- Authentication: `Yes`
- Permission: `driver_manifests.create`
- Purpose: Creates a daily manifest, attaches shipment snapshots, calculates initial expected COD, and moves selected shipments to `out_for_delivery`.

Request body example:

```json
{
  "driver_id": 1,
  "manifest_date": "2026-05-23",
  "shipment_ids": [1, 2, 3],
  "notes": "Morning loadout"
}
```

Rules:

- Shipments must be `ready_for_dispatch` or `out_for_delivery`
- One driver cannot have two open manifests on the same date
- A shipment cannot belong to another open manifest

Success response example:

```json
{
  "data": {
    "id": 1,
    "manifest_number": "DM-20260523-000001",
    "manifest_date": "2026-05-23",
    "status": "open",
    "driver": {
      "id": 1,
      "name": "Mahmoud Hassan",
      "phone": "01012345678",
      "vehicle_type": "Motorcycle",
      "vehicle_plate": "ABC-1234"
    },
    "summary": {
      "total_shipments": 3,
      "expected_cod_amount": "15000.00",
      "actual_received_amount": null,
      "difference_amount": "0.00",
      "closed_shipments_count": 0
    },
    "notes": "Morning loadout",
    "items": [
      {
        "id": 1,
        "shipment_id": 1,
        "shipment_number": "SHP-20260523-000001",
        "order_id": 1,
        "order_number": "ORD-20260523-000001",
        "merchant_name": "Fast Trade",
        "customer_name": "Ahmed Adel",
        "customer_phone": "01000000000",
        "delivery_address": "Nasr City, Cairo",
        "cod_amount": "5000.00",
        "shipping_fee": "80.00",
        "shipment_status_at_loadout": "ready_for_dispatch",
        "final_status": null,
        "delivered_cod_amount": "0.00",
        "notes": null,
        "created_at": "2026-05-23T09:00:00.000000Z"
      }
    ],
    "created_by": 2,
    "created_by_name": "Operations User",
    "closed_by": null,
    "closed_by_name": null,
    "closed_at": null,
    "created_at": "2026-05-23T09:00:00.000000Z"
  }
}
```

### GET `/api/driver-manifests`

- Method: `GET`
- Authentication: `Yes`
- Permission: `driver_manifests.view`
- Purpose: Returns daily manifests with closure and shipment snapshot data.
- Optional filters:
- `driver_id`
- `manifest_date`
- `status`
- `date_from`
- `date_to`

### GET `/api/driver-manifests/{manifest}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `driver_manifests.view`
- Purpose: Returns one manifest with driver, items, financial values, and closure information.

### POST `/api/driver-manifests/{manifest}/close`

- Method: `POST`
- Authentication: `Yes`
- Permission: `driver_manifests.close`
- Purpose: Closes the manifest after all shipments have final daily outcomes, stores actual cash received, calculates discrepancy, and syncs `driver_cash_closure`.

Request body example:

```json
{
  "actual_received_amount": 14800,
  "notes": "Driver returned successfully"
}
```

Manifest closing rules:

- All shipments must have final daily operational status before closing
- Expected COD is recalculated from delivered item results when available
- `difference_amount = actual_received_amount - expected_cod_amount`
- A linked `driver_cash_closure` is created or updated automatically

Success response example:

```json
{
  "data": {
    "id": 1,
    "manifest_number": "DM-20260523-000001",
    "manifest_date": "2026-05-23",
    "status": "closed",
    "driver": {
      "id": 1,
      "name": "Mahmoud Hassan",
      "phone": "01012345678",
      "vehicle_type": "Motorcycle",
      "vehicle_plate": "ABC-1234"
    },
    "summary": {
      "total_shipments": 3,
      "expected_cod_amount": "15000.00",
      "actual_received_amount": "14800.00",
      "difference_amount": "-200.00",
      "closed_shipments_count": 3
    },
    "notes": "Driver returned successfully",
    "cash_closure": {
      "id": 7,
      "driver_id": 1,
      "driver_name": "Mahmoud Hassan",
      "driver_manifest_id": 1,
      "manifest_number": "DM-20260523-000001",
      "date": "2026-05-23",
      "expected_amount": "15000.00",
      "received_amount": "14800.00",
      "difference_amount": "-200.00",
      "status": "verified"
    },
    "closed_by": 2,
    "closed_by_name": "Operations User",
    "closed_at": "2026-05-23T19:00:00.000000Z"
  }
}
```

### GET `/api/driver-manifests/{manifest}/print`

- Method: `GET`
- Authentication: `Yes`
- Permission: `driver_manifests.print`
- Purpose: Returns printable manifest JSON for frontend PDF or print rendering.

Success response example:

```json
{
  "data": {
    "manifest": {
      "manifest_number": "DM-20260523-000001",
      "date": "2026-05-23",
      "driver_name": "Mahmoud Hassan",
      "driver_phone": "01012345678",
      "total_shipments": 3,
      "expected_cod_amount": "15000.00",
      "status": "open",
      "notes": "Morning loadout"
    },
    "shipments": [
      {
        "shipment_number": "SHP-20260523-000001",
        "order_number": "ORD-20260523-000001",
        "customer_name": "Ahmed Adel",
        "customer_phone": "01000000000",
        "delivery_address": "Nasr City, Cairo",
        "cod_amount": "5000.00",
        "shipping_fee": "80.00",
        "merchant_name": "Fast Trade",
        "notes": null
      }
    ],
    "summary": {
      "total_cod": "15000.00",
      "total_shipments": 3,
      "delivered_shipments": 0,
      "failed_shipments": 0
    }
  }
}
```

### GET `/api/merchants/{merchant}/dashboard/summary`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns merchant-scoped dashboard summary cards using only this merchant's shipments.

Card definitions:

- `company_profit`: sum of `shipping_fee` for delivered shipments of this merchant.
- `cod_collected`: sum of `cod_amount` for delivered shipments of this merchant where `order.payment_type = cod`.
- `merchants_payables`: sum of `max(cod_amount - shipping_fee, 0)` for delivered shipments of this merchant.
- Shipment counts use only this merchant's shipments.

Optional query filters:

- `date_from`
- `date_to`
- `assigned_driver_id`

Request examples:

```text
GET /api/merchants/1/dashboard/summary
GET /api/merchants/1/dashboard/summary?date_from=2026-04-01&date_to=2026-04-24
GET /api/merchants/1/dashboard/summary?assigned_driver_id=1
```

Success response example:

```json
{
  "data": {
    "company_profit": "120.00",
    "merchants_payables": "940.00",
    "cod_collected": "1000.00",
    "shipments": {
      "total": 10,
      "pending_pickup": 2,
      "in_transit": 3,
      "delivered": 4,
      "assigned": 6,
      "unassigned": 4
    },
    "filters": {
      "date_from": null,
      "date_to": null,
      "merchant_id": 1,
      "assigned_driver_id": null
    }
  }
}
```

Common validation error response example:

```json
{
  "message": "The date to field must be a date after or equal to date from.",
  "errors": {
    "date_to": [
      "The date to field must be a date after or equal to date from."
    ]
  }
}
```

### GET `/api/drivers/{driver}/shipments`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns all shipments assigned to the selected driver with merchant, delivery location, assigned driver, and histories loaded.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "shipment_number": "SHP-20260424-000001",
      "merchant_id": 1,
      "merchant_name": "Fast Trade",
      "customer_name": "Ahmed Adel",
      "customer_phone": "01000000000",
      "delivery_governorate_id": 2,
      "delivery_governorate_name": "Cairo",
      "delivery_area_id": 5,
      "delivery_area_name": "Nasr City",
      "delivery_address": "Building 10, Street 12, Cairo",
      "cod_amount": "450.00",
      "shipping_fee": "80.00",
      "status": "pending_pickup",
      "tracking_notes": null,
      "assigned_driver_id": 1,
      "assigned_driver_name": "Mahmoud Hassan",
      "histories": [
        {
          "id": 1,
          "shipment_id": 1,
          "status": "pending_pickup",
          "notes": "Shipment created from order.",
          "changed_by": 1,
          "changed_by_name": "Admin User",
          "created_at": "2026-04-24T15:00:00.000000Z"
        }
      ],
      "created_at": "2026-04-24T15:00:00.000000Z"
    }
  ]
}
```

### GET `/api/drivers/{driver}/manifest`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns a printable JSON manifest for a driver with shipment list, totals, and merchant phone for operational support.
- Merchant phone is included so the driver can contact the merchant when the customer is not responding.
- `total_cod_shipments` counts assigned shipments where `shipment -> order -> payment_type = cod`.
- `total_prepaid_shipments` counts assigned shipments where `shipment -> order -> payment_type = prepaid`.
- Optional query params:
- `status`
- `date` in `YYYY-MM-DD`

Request examples:

```text
GET /api/drivers/1/manifest
GET /api/drivers/1/manifest?status=pending_pickup
GET /api/drivers/1/manifest?date=2026-04-24
```

Success response example:

```json
{
  "data": {
    "driver": {
      "id": 1,
      "name": "Mahmoud Hassan",
      "phone": "01012345678",
      "vehicle_type": "Motorcycle",
      "vehicle_plate": "ABC-1234"
    },
    "generated_at": "2026-04-24T17:00:00.000000Z",
    "summary": {
      "total_shipments": 2,
      "total_cod_shipments": 1,
      "total_prepaid_shipments": 1,
      "total_cod_amount": "650.00",
      "total_shipping_fee": "140.00"
    },
    "shipments": [
      {
        "shipment_id": 1,
        "shipment_number": "SHP-20260424-000001",
        "status": "pending_pickup",
        "customer_name": "Ahmed Adel",
        "customer_phone": "01000000000",
        "delivery_governorate_name": "Cairo",
        "delivery_area_name": "Nasr City",
        "delivery_address": "Building 10, Street 12, Cairo",
        "cod_amount": "450.00",
        "shipping_fee": "80.00",
        "merchant_name": "Fast Trade",
        "merchant_phone": "0223456789",
        "notes": null
      },
      {
        "shipment_id": 2,
        "shipment_number": "SHP-20260424-000002",
        "status": "pending_pickup",
        "customer_name": "Mona Samir",
        "customer_phone": "01222222222",
        "delivery_governorate_name": "Giza",
        "delivery_area_name": null,
        "delivery_address": "Haram Street",
        "cod_amount": "200.00",
        "shipping_fee": "60.00",
        "merchant_name": "Fast Trade",
        "merchant_phone": "0223456789",
        "notes": "Leave at reception"
      }
    ]
  }
}
```

Common validation error response example:

```json
{
  "message": "The selected status is invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

## Other Existing API Route

## Dashboard APIs

### GET `/api/dashboard/summary`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns admin dashboard summary cards based on existing shipments data.

Card definitions:

- `company_profit`: sum of `shipping_fee` for delivered shipments.
- `merchants_payables`: sum of `max(cod_amount - shipping_fee, 0)` for delivered shipments.
- `cod_collected`: sum of `cod_amount` for delivered shipments.
- `shipments.total`: count of all shipments.
- `shipments.pending_pickup`: count of shipments with status `pending_pickup`.
- `shipments.in_transit`: count of shipments with status `pending_pickup`, `picked_up`, `in_transit`, or `out_for_delivery`.
- `shipments.delivered`: count of shipments with status `delivered`.
- `shipments.assigned`: count of shipments where `assigned_driver_id` is not `null`.
- `shipments.unassigned`: count of shipments where `assigned_driver_id` is `null`.

Optional query filters:

- `date_from`
- `date_to`
- `merchant_id`
- `assigned_driver_id`

Request examples:

```text
GET /api/dashboard/summary
GET /api/dashboard/summary?date_from=2026-04-01&date_to=2026-04-24
GET /api/dashboard/summary?merchant_id=1
GET /api/dashboard/summary?assigned_driver_id=1
```

Success response example:

```json
{
  "data": {
    "company_profit": "120.00",
    "merchants_payables": "940.00",
    "cod_collected": "1000.00",
    "shipments": {
      "total": 10,
      "pending_pickup": 2,
      "in_transit": 3,
      "delivered": 4,
      "assigned": 6,
      "unassigned": 4
    },
    "filters": {
      "date_from": null,
      "date_to": null,
      "merchant_id": null,
      "assigned_driver_id": null
    }
  }
}
```

Common validation error response example:

```json
{
  "message": "The date to field must be a date after or equal to date from.",
  "errors": {
    "date_to": [
      "The date to field must be a date after or equal to date from."
    ]
  }
}
```

## Finance APIs

### Finance Rules

- Finance Phase 1 uses existing delivered shipments and merchant payouts only.
- Prepaid delivered shipments contribute to company shipping profit, but not to merchant payable or COD collected.
- Cancelled payouts do not count in `paid_out`.
- Finance Ledger Phase adds merchant wallet transactions beside the legacy shipment-based summaries.
- Approved wallet `credit` transactions increase merchant wallet balance.
- Approved wallet `debit` transactions decrease merchant wallet balance.
- Pending or rejected wallet transactions do not affect available balance.
- Wallet transaction types include COD credits, service fees, warehouse fees, payouts, manual adjustments, refunds, and penalties.

### Merchant Wallet Notes

- Each merchant now has a ledger-style wallet visible through summary and transaction endpoints.
- Manual deposit and withdraw requests are created from admin actions and can stay `pending` until approved.
- System-generated transactions are used for:
- delivered COD credits
- shipping, return, and swap fee debits
- warehouse fee debits
- payout debits
- Invoice generation now creates itemized lines from approved wallet transactions when available.

### GET `/api/merchants/{merchant}/wallet/summary`

- Method: `GET`
- Authentication: `Yes`
- Permission: `merchant_wallet.view`
- Purpose: Returns wallet balances and merchant ledger totals.

Success response example:

```json
{
  "data": {
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "available_balance": "4000.00",
    "pending_credits": "500.00",
    "pending_debits": "200.00",
    "total_cod_collected": "5000.00",
    "total_fees": "800.00",
    "total_payouts": "1000.00",
    "total_manual_deposits": "500.00",
    "total_manual_withdraws": "200.00",
    "total_wallet_credits": "5500.00",
    "total_wallet_debits": "1500.00"
  }
}
```

### GET `/api/merchants/{merchant}/wallet/transactions`

- Method: `GET`
- Authentication: `Yes`
- Permission: `merchant_wallet.view`
- Purpose: Returns wallet ledger entries for one merchant.
- Optional filters:
- `transaction_type`
- `direction`
- `status`
- `date_from`
- `date_to`
- `source_type`

### POST `/api/merchants/{merchant}/wallet/deposit`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_wallet.deposit`
- Purpose: Creates a manual wallet credit transaction.

Request body example:

```json
{
  "amount": 500,
  "reason": "Compensation",
  "notes": "Manual compensation for delayed delivery",
  "reference_number": "ADJ-001"
}
```

### POST `/api/merchants/{merchant}/wallet/withdraw`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_wallet.withdraw`
- Purpose: Creates a manual wallet debit transaction.

Request body example:

```json
{
  "amount": 200,
  "reason": "Damaged packaging penalty",
  "notes": "Manual deduction",
  "reference_number": "PEN-001"
}
```

### GET `/api/merchant-wallet-transactions/{transaction}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `merchant_wallet.view`
- Purpose: Returns one wallet transaction with approval and audit fields.

### POST `/api/merchant-wallet-transactions/{transaction}/approve`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_wallet.approve`
- Purpose: Approves a pending wallet transaction and stores `balance_after`.

### POST `/api/merchant-wallet-transactions/{transaction}/reject`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_wallet.reject`
- Purpose: Rejects a pending wallet transaction without affecting balance.

Request body example:

```json
{
  "rejection_reason": "Invalid adjustment"
}
```

### GET `/api/merchant-payouts`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns merchant payouts with optional filters.
- Optional filters:
- `merchant_id`
- `status`
- `payment_method`
- `date_from`
- `date_to`

Request examples:

```text
GET /api/merchant-payouts
GET /api/merchant-payouts?merchant_id=1
GET /api/merchant-payouts?status=completed
GET /api/merchant-payouts?payment_method=bank_transfer
GET /api/merchant-payouts?date_from=2026-04-01&date_to=2026-04-24
```

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "merchant_id": 1,
      "merchant_name": "Ahmed Store",
      "amount": "2000.00",
      "status": "completed",
      "payment_method": "bank_transfer",
      "reference_number": "TRX-1001",
      "notes": "First payout batch",
      "created_by": 1,
      "created_by_name": "Admin User",
      "paid_at": "2026-04-24T18:00:00.000000Z",
      "created_at": "2026-04-24T17:30:00.000000Z"
    }
  ]
}
```

### POST `/api/merchant-payouts`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a merchant payout record.

Request body example:

```json
{
  "merchant_id": 1,
  "amount": 2000,
  "status": "completed",
  "payment_method": "bank_transfer",
  "reference_number": "TRX-1001",
  "notes": "First payout batch"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "amount": "2000.00",
    "status": "completed",
    "payment_method": "bank_transfer",
    "reference_number": "TRX-1001",
    "notes": "First payout batch",
    "created_by": 1,
    "created_by_name": "Admin User",
    "paid_at": "2026-04-24T18:00:00.000000Z",
    "created_at": "2026-04-24T17:30:00.000000Z"
  }
}
```

### GET `/api/merchant-payouts/{merchantPayout}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one merchant payout by ID.

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "amount": "2000.00",
    "status": "completed",
    "payment_method": "bank_transfer",
    "reference_number": "TRX-1001",
    "notes": "First payout batch",
    "created_by": 1,
    "created_by_name": "Admin User",
    "paid_at": "2026-04-24T18:00:00.000000Z",
    "created_at": "2026-04-24T17:30:00.000000Z"
  }
}
```

### PUT `/api/merchant-payouts/{merchantPayout}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates a merchant payout. If status becomes `completed` and `paid_at` is not sent, `paid_at` is set automatically.

Request body example:

```json
{
  "status": "completed",
  "payment_method": "cash",
  "notes": "Paid in office"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "amount": "2000.00",
    "status": "completed",
    "payment_method": "cash",
    "reference_number": "TRX-1001",
    "notes": "Paid in office",
    "created_by": 1,
    "created_by_name": "Admin User",
    "paid_at": "2026-04-24T18:15:00.000000Z",
    "created_at": "2026-04-24T17:30:00.000000Z"
  }
}
```

### DELETE `/api/merchant-payouts/{merchantPayout}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a merchant payout.

Success response example:

```json
{
  "message": "Merchant payout deleted successfully"
}
```

### Merchant Payout Approval Flow

- Payout statuses now support: `pending`, `approved`, `completed`, `rejected`, `cancelled`.
- `pending` and `approved` payouts do not affect merchant wallet balance.
- `completed` payouts create an approved wallet debit transaction and a treasury debit transaction when a treasury account is available.
- Rejected or cancelled payouts do not affect wallet balance.

### POST `/api/merchant-payouts/{merchantPayout}/approve`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.payouts.approve`
- Purpose: Approves a pending payout for payment preparation.

### POST `/api/merchant-payouts/{merchantPayout}/reject`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.payouts.reject`
- Purpose: Rejects a pending or approved payout.

Request body example:

```json
{
  "rejection_reason": "Balance mismatch"
}
```

### POST `/api/merchant-payouts/{merchantPayout}/complete`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.payouts.complete`
- Purpose: Completes an approved payout, checks merchant available wallet balance, and posts ledger and treasury entries.

Request body example:

```json
{
  "treasury_account_id": 1,
  "payment_method": "bank_transfer",
  "reference_number": "TRX-2001",
  "notes": "Paid by finance team"
}
```

### GET `/api/merchants/{merchant}/finance/summary`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns merchant financial summary based on delivered shipments and merchant payouts.

Calculation formulas:

- `cod_collected`: sum of `cod_amount` for delivered shipments where `order.payment_type = cod`.
- `shipping_fees`: sum of `shipping_fee` for delivered shipments.
- `merchant_payable`: sum of `max(cod_amount - shipping_fee, 0)` for delivered shipments.
- `paid_out`: sum of completed merchant payouts.
- `pending_payouts`: sum of pending merchant payouts.
- `remaining_balance`: `merchant_payable - paid_out - pending_payouts`.
- `company_profit_from_shipping`: same as `shipping_fees`.

Optional query filters:

- `date_from`
- `date_to`

Request examples:

```text
GET /api/merchants/1/finance/summary
GET /api/merchants/1/finance/summary?date_from=2026-04-01&date_to=2026-04-24
```

Success response example:

```json
{
  "data": {
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "cod_collected": "5000.00",
    "shipping_fees": "600.00",
    "merchant_payable": "4400.00",
    "paid_out": "2000.00",
    "pending_payouts": "500.00",
    "remaining_balance": "1900.00",
    "legacy_remaining_balance": "1900.00",
    "wallet_available_balance": "4000.00",
    "wallet_pending_credits": "500.00",
    "wallet_pending_debits": "200.00",
    "total_wallet_credits": "5500.00",
    "total_wallet_debits": "1500.00",
    "company_profit_from_shipping": "600.00",
    "shipments": {
      "delivered_count": 10,
      "cod_delivered_count": 6,
      "prepaid_delivered_count": 4
    },
    "filters": {
      "date_from": null,
      "date_to": null
    }
  }
}
```

Common validation error response example:

```json
{
  "message": "The date to field must be a date after or equal to date from.",
  "errors": {
    "date_to": [
      "The date to field must be a date after or equal to date from."
    ]
  }
}
```

### GET `/api/driver-cash-closures`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns driver cash closures with optional filters.
- Optional filters:
- `driver_id`
- `status`
- `date_from`
- `date_to`

### POST `/api/driver-cash-closures`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a driver cash closure record.
- `difference_amount` is calculated as `received_amount - expected_amount`.
- If status is `verified`, `verified_by` and `verified_at` are set automatically.

Request body example:

```json
{
  "driver_id": 1,
  "expected_amount": 5000,
  "received_amount": 4800,
  "status": "verified",
  "notes": "Cash counted in office"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "driver_id": 1,
    "driver_name": "Mahmoud Hassan",
    "expected_amount": "5000.00",
    "received_amount": "4800.00",
    "difference_amount": "-200.00",
    "status": "verified",
    "notes": "Cash counted in office",
    "created_by": 1,
    "created_by_name": "Admin User",
    "verified_by": 1,
    "verified_by_name": "Admin User",
    "verified_at": "2026-04-24T19:00:00.000000Z",
    "created_at": "2026-04-24T19:00:00.000000Z"
  }
}
```

### GET `/api/driver-cash-closures/{driverCashClosure}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one driver cash closure.

### PUT `/api/driver-cash-closures/{driverCashClosure}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates a driver cash closure and recalculates `difference_amount`.

### DELETE `/api/driver-cash-closures/{driverCashClosure}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a driver cash closure.

### GET `/api/drivers/{driver}/cash-expected`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Calculates expected COD cash for a driver from delivered COD shipments assigned to that driver.

Success response example:

```json
{
  "data": {
    "driver_id": 1,
    "expected_amount": "5000.00"
  }
}
```

### GET `/api/expenses`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns expenses with optional filters.
- Optional filters:
- `category`
- `status`
- `payment_method`
- `date_from`
- `date_to`

### POST `/api/expenses`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates an expense record.

Request body example:

```json
{
  "category": "fuel",
  "amount": 300,
  "expense_date": "2026-04-24",
  "payment_method": "cash",
  "reference_number": "EXP-1001",
  "notes": "Delivery fuel refill"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "category": "fuel",
    "amount": "300.00",
    "expense_date": "2026-04-24",
    "payment_method": "cash",
    "reference_number": "EXP-1001",
    "notes": "Delivery fuel refill",
    "created_by": 1,
    "created_by_name": "Admin User",
    "created_at": "2026-04-24T19:15:00.000000Z"
  }
}
```

### GET `/api/expenses/{expense}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one expense.

### PUT `/api/expenses/{expense}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates an expense.

### DELETE `/api/expenses/{expense}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes an expense.

### Expense Approval Flow

- Expense statuses now support: `pending`, `approved`, `paid`, `rejected`, `cancelled`.
- Only `paid` expenses affect company profit and treasury cash reports.
- Approved expenses can be marked paid through a dedicated endpoint.

### POST `/api/expenses/{expense}/approve`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.expenses.approve`
- Purpose: Approves a pending expense.

### POST `/api/expenses/{expense}/reject`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.expenses.reject`
- Purpose: Rejects a pending or approved expense.

Request body example:

```json
{
  "rejection_reason": "Missing receipt"
}
```

### POST `/api/expenses/{expense}/mark-paid`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.expenses.pay`
- Purpose: Marks an approved expense as paid and creates a treasury debit transaction when a treasury account is available.

Request body example:

```json
{
  "treasury_account_id": 1,
  "notes": "Paid from cashbox"
}
```

### GET `/api/finance/company-profit-summary`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns company profit summary from delivered shipments and expenses.

Calculation formulas:

- `total_shipping_fees`: sum of `shipping_fee` for delivered shipments.
- `total_expenses`: sum of `expenses.amount`.
- `net_company_profit`: `total_shipping_fees - total_expenses`.
- `total_cod_collected`: sum of `cod_amount` for delivered COD shipments.
- `delivered_shipments_count`: count delivered shipments.

Request examples:

```text
GET /api/finance/company-profit-summary
GET /api/finance/company-profit-summary?date_from=2026-04-01&date_to=2026-04-24
```

Success response example:

```json
{
  "data": {
    "total_shipping_fees": "1000.00",
    "total_expenses": "300.00",
    "net_company_profit": "700.00",
    "total_cod_collected": "5000.00",
    "delivered_shipments_count": 20,
    "filters": {
      "date_from": null,
      "date_to": null
    }
  }
}
```

### GET `/api/merchant-invoices`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns merchant invoices with optional filters.
- Optional filters:
- `merchant_id`
- `status`
- `date_from`
- `date_to`

### POST `/api/merchant-invoices`

- Method: `POST`
- Authentication: `Yes`
- Purpose: Creates a merchant invoice and calculates totals from delivered shipments within the period.
- Invoice number is generated automatically in format `INV-YYYYMMDD-000001`.
- If status is `issued`, `issued_at` is set automatically.

Request body example:

```json
{
  "merchant_id": 1,
  "period_start": "2026-04-01",
  "period_end": "2026-04-24",
  "status": "issued",
  "notes": "April statement"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "invoice_number": "INV-20260424-000001",
    "period_start": "2026-04-01",
    "period_end": "2026-04-24",
    "total_cod": "5000.00",
    "total_shipping_fees": "600.00",
    "total_payable": "4400.00",
    "status": "issued",
    "notes": "April statement",
    "created_by": 1,
    "created_by_name": "Admin User",
    "issued_at": "2026-04-24T19:30:00.000000Z",
    "created_at": "2026-04-24T19:30:00.000000Z"
  }
}
```

### GET `/api/merchant-invoices/{merchantInvoice}`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns one merchant invoice.
- Response does not expose the raw stored invoice file path. Use the dedicated preview or download endpoints.

### PUT `/api/merchant-invoices/{merchantInvoice}`

- Method: `PUT`
- Authentication: `Yes`
- Purpose: Updates a merchant invoice and recalculates totals from delivered shipments for the selected period.

### DELETE `/api/merchant-invoices/{merchantInvoice}`

- Method: `DELETE`
- Authentication: `Yes`
- Purpose: Soft deletes a merchant invoice.

### Invoice Itemization Notes

- Merchant invoices now include `items`.
- Invoice items are generated from approved merchant wallet transactions within the invoice period when available.
- Legacy shipment and warehouse totals are still kept on the invoice for backward compatibility.

### GET `/api/finance/reconciliation-summary`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns high-level reconciliation totals between expected COD, verified driver cash, closed driver manifests, and merchant payouts.

Calculation formulas:

- `total_cod_expected`: sum of `cod_amount` for delivered COD shipments.
- `total_driver_cash_verified`: sum of verified driver cash closure `received_amount`.
- `cod_difference`: `total_driver_cash_verified - total_cod_expected`.
- `driver_manifests_closed`: count of closed driver manifests in the selected period.
- `driver_manifest_expected_cod`: sum of closed manifest expected COD amounts.
- `driver_manifest_actual_received`: sum of closed manifest actual received amounts.
- `driver_manifest_discrepancy_total`: sum of closed manifest discrepancy amounts.
- `wallet_total_credits`: sum of approved merchant wallet credits.
- `wallet_total_debits`: sum of approved merchant wallet debits.
- `wallet_available_total`: approved credits minus approved debits.
- `payout_pending_total`: sum of pending payouts.
- `payout_approved_total`: sum of approved payouts.
- `payout_completed_total`: sum of completed payouts.
- `treasury_accounts_total`: count of treasury accounts.
- `treasury_current_balance_total`: sum of treasury account current balances.
- `total_merchant_payable`: sum of `max(cod_amount - shipping_fee, 0)` for delivered shipments.
- `total_merchant_paid_out`: sum of completed merchant payouts.
- `merchant_balance_remaining`: `total_merchant_payable - total_merchant_paid_out`.

Request examples:

```text
GET /api/finance/reconciliation-summary
GET /api/finance/reconciliation-summary?date_from=2026-04-01&date_to=2026-04-24
```

Success response example:

```json
{
  "data": {
    "total_cod_expected": "5000.00",
    "total_driver_cash_verified": "4800.00",
    "cod_difference": "-200.00",
    "driver_manifests_closed": 4,
    "driver_manifest_expected_cod": "5000.00",
    "driver_manifest_actual_received": "4800.00",
    "driver_manifest_discrepancy_total": "-200.00",
    "wallet_total_credits": "5500.00",
    "wallet_total_debits": "1500.00",
    "wallet_available_total": "4000.00",
    "payout_pending_total": "500.00",
    "payout_approved_total": "700.00",
    "payout_completed_total": "2000.00",
    "treasury_accounts_total": 2,
    "treasury_current_balance_total": "12500.00",
    "total_merchant_payable": "4400.00",
    "total_merchant_paid_out": "2000.00",
    "merchant_balance_remaining": "2400.00",
    "filters": {
      "date_from": null,
      "date_to": null
    }
  }
}
```

### Treasury APIs

### GET `/api/treasury-accounts`

- Method: `GET`
- Authentication: `Yes`
- Permission: `finance.treasury.view`
- Purpose: Returns treasury accounts with balances and status.

### POST `/api/treasury-accounts`

- Method: `POST`
- Authentication: `Yes`
- Permission: `finance.treasury.create`
- Purpose: Creates a treasury account.

Request body example:

```json
{
  "name": "Main Cashbox",
  "code": "CASH-001",
  "type": "cash",
  "opening_balance": 10000,
  "status": "active"
}
```

### GET `/api/treasury-accounts/{treasuryAccount}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `finance.treasury.view`

### PUT `/api/treasury-accounts/{treasuryAccount}`

- Method: `PUT`
- Authentication: `Yes`
- Permission: `finance.treasury.update`

### DELETE `/api/treasury-accounts/{treasuryAccount}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `finance.treasury.delete`

### GET `/api/treasury-transactions`

- Method: `GET`
- Authentication: `Yes`
- Permission: `finance.treasury.view`
- Purpose: Returns treasury cash movements.
- Optional filters:
- `treasury_account_id`
- `direction`
- `source_type`
- `date_from`
- `date_to`

### Finance Report APIs

### GET `/api/finance/reports/daily-cash`

- Permission: `finance.reports.daily_cash`
- Purpose: Returns treasury cash-in, cash-out, and net cash summary.

### GET `/api/finance/reports/merchant-ledger`

- Permission: `finance.reports.merchant_ledger`
- Purpose: Returns wallet-ledger transactions and summary totals.

### GET `/api/finance/reports/driver-discrepancies`

- Permission: `finance.reports.driver_discrepancies`
- Purpose: Returns closed manifest discrepancy totals by driver and manifest.

### GET `/api/finance/reports/warehouse-revenue`

- Permission: `finance.reports.warehouse_revenue`
- Purpose: Returns storage, pick, pack, and warehouse-charge revenue totals.

### GET `/api/finance/reports/profit-loss`

- Permission: `finance.reports.profit_loss`
- Purpose: Returns shipping revenue, warehouse revenue, paid expenses, net profit, and treasury net cash totals.

### GET `/api/user`

- Method: `GET`
- Authentication: `Yes`
- Purpose: Returns the authenticated user from the default route defined in `routes/api.php`.

Success response example:

```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "role": "admin",
  "email_verified_at": null,
  "created_at": "2026-04-24T10:00:00.000000Z",
  "updated_at": "2026-04-24T10:00:00.000000Z",
  "deleted_at": null
}
```

Common error response example:

```json
{
  "message": "Unauthenticated."
}
```

## Barcode APIs

### Barcode Notes

- `orders`, `shipments`, and `warehouse_products` now include a separate `barcode` field in their API resources.
- Barcodes are different from `order_number` and `shipment_number`.
- Scanner devices are treated as keyboard input. No special hardware SDK is required.
- Barcode formats:
- Order: `ORD-BC-YYYYMMDD-000001`
- Shipment: `SHP-BC-YYYYMMDD-000001`
- Warehouse Product: `WH-PROD-{merchant_id}-YYYYMMDD-000001`
- Warehouse Bin: `BIN-BC-YYYYMMDD-000001`

### POST `/api/barcodes/lookup`

- Method: `POST`
- Authentication: `Yes`
- Permission: `barcodes.lookup`
- Purpose: Looks up a barcode across supported entities and returns the matching payload.

Request body example:

```json
{
  "barcode": "SHP-BC-20260523-000001"
}
```

Success response example:

```json
{
  "data": {
    "type": "shipment",
    "id": 1,
    "barcode": "SHP-BC-20260523-000001",
    "data": {
      "id": 1,
      "shipment_number": "SHP-20260523-000001",
      "barcode": "SHP-BC-20260523-000001",
      "status": "picked_up"
    }
  }
}
```

Not found response example:

```json
{
  "message": "Barcode not found."
}
```

### POST `/api/barcodes/scan/shipment-status`

- Method: `POST`
- Authentication: `Yes`
- Permission: `barcodes.scan`
- Purpose: Looks up a shipment by barcode and updates its status using the standard shipment status workflow.

Request body example:

```json
{
  "barcode": "SHP-BC-20260523-000001",
  "status": "picked_up",
  "notes": "Scanned at pickup"
}
```

### POST `/api/barcodes/scan/product-lookup`

- Method: `POST`
- Authentication: `Yes`
- Permission: `barcodes.scan`
- Purpose: Looks up a warehouse product by barcode and returns the product with its inventory stocks and bin location when available.

Request body example:

```json
{
  "barcode": "WH-PROD-1-20260523-000001"
}
```

## Audit Logs APIs

### Audit Logs Concept

- Audit logs are now centralized and reusable across modules.
- The system records both automatic model lifecycle events and manual business workflow events.
- Global audit logs support accountability, finance traceability, operational traceability, and security review.
- Entity timelines let the frontend render activity history for a shipment, order, merchant, warehouse product, payout, expense, or other tracked entity.

### Security Notes

- Sensitive fields are excluded automatically and are not stored in audit payloads.
- Excluded fields include examples such as `password`, `remember_token`, `token`, `api_token`, `api_key`, and `secret`.
- Request context stores IP address and user agent when available.
- Audit logs are append-oriented and designed for future archive or export support.

### Event Type Examples

- `order_created`
- `order_updated`
- `order_confirmed`
- `order_cancelled`
- `shipment_created`
- `shipment_status_updated`
- `shipment_driver_assigned`
- `delivery_attempt_created`
- `partial_delivery_completed`
- `wallet_transaction_created`
- `wallet_transaction_approved`
- `payout_created`
- `payout_completed`
- `expense_paid`
- `inventory_adjusted`
- `stock_reserved`
- `stock_released`
- `warehouse_return_created`
- `manifest_generated`
- `manifest_closed`
- `role_updated`
- `permission_updated`
- `login`
- `logout`

### GET `/api/audit-logs`

- Method: `GET`
- Authentication: `Yes`
- Permission: `audit_logs.view`
- Purpose: Returns global audit logs with pagination for compliance, support, and operational review.
- Optional filters:
- `user_id`
- `event_type`
- `action`
- `auditable_type`
- `auditable_id`
- `date_from`
- `date_to`
- `search`
- `per_page`

Search behavior:

- `search` matches `user_name`
- `search` matches `notes`
- `search` matches `event_type`

Success response example:

```json
{
  "data": [
    {
      "id": 15,
      "user": {
        "id": 2,
        "name": "Operations User"
      },
      "event_type": "shipment_driver_assigned",
      "action": "assigned_driver",
      "auditable_type": "shipment",
      "auditable_id": 15,
      "old_values": {
        "assigned_driver_id": null
      },
      "new_values": {
        "assigned_driver_id": 4
      },
      "metadata": {
        "driver_name": "Mahmoud Hassan",
        "request_method": "POST",
        "request_path": "api/shipments/15/assign-driver"
      },
      "notes": "Driver assigned to shipment.",
      "ip_address": "127.0.0.1",
      "created_at": "2026-05-23T10:30:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/audit-logs?page=1",
    "last": "http://localhost/api/audit-logs?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://localhost/api/audit-logs",
    "per_page": 50,
    "to": 1,
    "total": 1
  }
}
```

### GET `/api/audit-logs/timeline`

- Method: `GET`
- Authentication: `Yes`
- Permission: `audit_logs.timeline`
- Purpose: Returns a chronological activity timeline for one auditable entity.
- Required query params:
- `auditable_type`
- `auditable_id`

Request example:

```text
GET /api/audit-logs/timeline?auditable_type=shipment&auditable_id=15
```

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "name": "Admin User"
      },
      "event_type": "shipment_created",
      "action": "created",
      "auditable_type": "shipment",
      "auditable_id": 15,
      "old_values": null,
      "new_values": {
        "status": "pending_pickup"
      },
      "metadata": {
        "request_method": "POST",
        "request_path": "api/orders/10/shipments"
      },
      "notes": null,
      "ip_address": "127.0.0.1",
      "created_at": "2026-05-23T10:00:00.000000Z"
    },
    {
      "id": 2,
      "user": {
        "id": 2,
        "name": "Operations User"
      },
      "event_type": "shipment_driver_assigned",
      "action": "assigned_driver",
      "auditable_type": "shipment",
      "auditable_id": 15,
      "old_values": {
        "assigned_driver_id": null
      },
      "new_values": {
        "assigned_driver_id": 4
      },
      "metadata": {
        "driver_name": "Mahmoud Hassan"
      },
      "notes": "Driver assigned to shipment.",
      "ip_address": "127.0.0.1",
      "created_at": "2026-05-23T10:30:00.000000Z"
    }
  ]
}
```

### Example Timeline Flow

Order example:

- `order_created`
- `order_confirmed`
- `shipment_created`
- `shipment_driver_assigned`
- `partial_delivery_completed`
- `wallet_transaction_created`

Merchant finance example:

- `wallet_transaction_created`
- `wallet_transaction_approved`
- `payout_created`
- `payout_completed`
- `treasury_transaction_created`

Warehouse example:

- `stock_reserved`
- `inventory_adjusted`
- `warehouse_return_created`
- `packing_completed`

## Export APIs

### Export System Notes

- Exports are centralized and logged in `export_logs`.
- Current implementation supports CSV generation only.
- Supported export types:
- `orders`
- `shipments`
- `driver_manifests`
- `merchant_wallet_transactions`
- `finance_reports`
- `warehouse_inventory`
- `treasury_transactions`
- `audit_logs`
- Files are generated under `storage/app/exports`.
- Every export creates an audit event `export_generated`.

### POST `/api/exports/{type}`

- Method: `POST`
- Authentication: `Yes`
- Permission: `exports.create`
- Purpose: Generates a CSV export for the selected resource type.

Request body example:

```json
{
  "format": "csv",
  "filters": {
    "date_from": "2026-05-01",
    "date_to": "2026-05-31"
  }
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "export_type": "shipments",
    "format": "csv",
    "filters": {
      "date_from": "2026-05-01",
      "date_to": "2026-05-31"
    },
    "status": "completed",
    "download_url": "http://localhost/api/exports/1/download",
    "generated_by_name": "Admin User",
    "generated_at": "2026-05-23T10:15:00.000000Z",
    "created_at": "2026-05-23T10:15:00.000000Z"
  }
}
```

### GET `/api/exports`

- Method: `GET`
- Authentication: `Yes`
- Permission: `exports.view`
- Purpose: Returns paginated export history.
- Optional filters:
- `export_type`
- `format`
- `status`
- `date_from`
- `date_to`
- `per_page`

### GET `/api/exports/{exportLog}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `exports.view`
- Purpose: Returns one export log record.
- Response does not expose the raw stored file path. Use `download_url` for access.

### GET `/api/exports/{exportLog}/download`

- Method: `GET`
- Authentication: `Yes`
- Permission: `exports.download`
- Purpose: Downloads the generated export file.

## Attachment APIs

### Attachment System Notes

- Attachments are generic and reusable across merchants, orders, shipments, expenses, merchant invoices, warehouse returns, treasury transactions, and driver manifests.
- Files are stored under `storage/app/attachments/{attachable_type}`.
- Allowed file types:
- `pdf`
- `jpg`
- `jpeg`
- `png`
- `webp`
- `csv`
- `xlsx`
- Maximum file size is `10 MB`.
- Every upload and delete action creates audit events `attachment_uploaded` and `attachment_deleted`.

### POST `/api/attachments`

- Method: `POST`
- Authentication: `Yes`
- Permission: `attachments.upload`
- Purpose: Uploads a file and links it to a supported entity.
- Content-Type: `multipart/form-data`

Multipart fields:

- `attachable_type`
- `attachable_id`
- `category`
- `file`
- `notes`

Example fields:

```text
attachable_type=expenses
attachable_id=5
category=receipt
notes=Fuel receipt image
file=(binary)
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "attachable_type": "expenses",
    "attachable_id": 5,
    "file_name": "b6bb3c4d-58a0-4a26-91d2-8e1d85b336cf.pdf",
    "original_name": "receipt.pdf",
    "mime_type": "application/pdf",
    "file_size": 204800,
    "category": "receipt",
    "notes": "Fuel receipt image",
    "download_url": "http://localhost/api/attachments/1/download",
    "uploaded_by_name": "Finance User",
    "created_at": "2026-05-23T11:00:00.000000Z"
  }
}
```

### GET `/api/attachments`

- Method: `GET`
- Authentication: `Yes`
- Permission: `attachments.view`
- Purpose: Returns paginated attachments with optional filters.
- Optional filters:
- `attachable_type`
- `attachable_id`
- `category`
- `per_page`

### GET `/api/attachments/{attachment}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `attachments.view`
- Purpose: Returns one attachment record.
- Response does not expose the raw stored file path. Use `download_url` for access.

### GET `/api/attachments/{attachment}/download`

- Method: `GET`
- Authentication: `Yes`
- Permission: `attachments.download`
- Purpose: Downloads the attachment file without exposing the internal absolute path.

### DELETE `/api/attachments/{attachment}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `attachments.delete`
- Purpose: Soft deletes the attachment and removes the stored file when present.

Success response example:

```json
{
  "message": "Attachment deleted successfully"
}
```

### Attachment Usage Examples

- Expense receipt upload
- Merchant contract or identity document
- Shipment paper scan
- Invoice support file
- Warehouse return photo

## Merchant Integration Token APIs

### Merchant Integration Token Concept

- Merchant integration tokens are secure admin-generated API tokens intended for future WooCommerce, Shopify, or custom storefront plugins.
- Tokens are created per merchant and can be revoked at any time.
- Only `token_hash` is stored in the database. The raw token is never stored.
- The raw token is returned only once at creation time.
- Tokens support scoped plugin permissions that are separate from internal admin permissions.
- No merchant password needs to be stored in a WordPress or external plugin.

### Token Providers

- `woocommerce`
- `shopify`
- `custom`

### Token Permissions

- `orders.create`
- `orders.view`
- `shipments.view`
- `tracking.view`
- `products.view`
- `inventory.view`

Default token permissions:

```json
[
  "orders.create",
  "orders.view",
  "shipments.view",
  "tracking.view"
]
```

### Security Notes

- Raw integration token is shown once only at creation.
- Stored database value is hash only.
- Revoked tokens immediately stop authenticating.
- Expired tokens immediately stop authenticating.
- Token prefix is display-only and helps support teams identify which token is being used.

### GET `/api/merchants/{merchant}/integration-tokens`

- Method: `GET`
- Authentication: `Yes`
- Permission: `merchant_integrations.view`
- Purpose: Lists merchant integration tokens for one merchant.
- `token_hash` is never returned.

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "merchant_id": 1,
      "merchant_name": "Ahmed Store",
      "name": "WooCommerce Main Store",
      "provider": "woocommerce",
      "token_prefix": "mit_live_ab12...",
      "permissions": [
        "orders.create",
        "orders.view",
        "shipments.view",
        "tracking.view"
      ],
      "status": "active",
      "last_used_at": null,
      "expires_at": null,
      "revoked_at": null,
      "revoked_by_name": null,
      "created_by_name": "Admin User",
      "notes": "Token for main WooCommerce store",
      "created_at": "2026-05-23T12:00:00.000000Z"
    }
  ]
}
```

### POST `/api/merchants/{merchant}/integration-tokens`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_integrations.create`
- Purpose: Creates a merchant integration token and returns the raw token once.

Request body example:

```json
{
  "name": "WooCommerce Main Store",
  "provider": "woocommerce",
  "permissions": [
    "orders.create",
    "orders.view",
    "shipments.view",
    "tracking.view"
  ],
  "expires_at": null,
  "notes": "Token for main WooCommerce store"
}
```

Success response example:

```json
{
  "data": {
    "id": 1,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "name": "WooCommerce Main Store",
    "provider": "woocommerce",
    "token_prefix": "mit_live_ab12...",
    "permissions": [
      "orders.create",
      "orders.view",
      "shipments.view",
      "tracking.view"
    ],
    "status": "active",
    "last_used_at": null,
    "expires_at": null,
    "revoked_at": null,
    "revoked_by_name": null,
    "created_by_name": "Admin User",
    "notes": "Token for main WooCommerce store",
    "created_at": "2026-05-23T12:00:00.000000Z",
    "plain_token": "mit_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }
}
```

### GET `/api/merchant-integration-tokens/{token}`

- Method: `GET`
- Authentication: `Yes`
- Permission: `merchant_integrations.view`
- Purpose: Returns one merchant integration token resource.

### POST `/api/merchant-integration-tokens/{token}/revoke`

- Method: `POST`
- Authentication: `Yes`
- Permission: `merchant_integrations.revoke`
- Purpose: Revokes an integration token so it can no longer authenticate.

Request body example:

```json
{
  "notes": "Store plugin was replaced"
}
```

### DELETE `/api/merchant-integration-tokens/{token}`

- Method: `DELETE`
- Authentication: `Yes`
- Permission: `merchant_integrations.delete`
- Purpose: Soft deletes an integration token record.

Success response example:

```json
{
  "message": "Merchant integration token deleted successfully"
}
```

### Plugin Authentication Header

- Header:
- `Authorization: Bearer mit_live_xxxxx`
- Optional alternative:
- `X-Merchant-Token: mit_live_xxxxx`

Authentication error examples:

```json
{
  "message": "Invalid integration token."
}
```

```json
{
  "message": "Integration token revoked."
}
```

```json
{
  "message": "Integration token expired."
}
```

### GET `/api/integrations/me`

- Method: `GET`
- Authentication: `Merchant integration token`
- Purpose: Test connection endpoint for future plugins.
- Rate limit: `throttle:integration-api` with the default limit sourced from `RATE_LIMIT_INTEGRATION_PER_MINUTE`.

Success response example:

```json
{
  "data": {
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "token_name": "WooCommerce Main Store",
    "provider": "woocommerce",
    "permissions": [
      "orders.create",
      "orders.view",
      "shipments.view",
      "tracking.view"
    ]
  }
}
```

### POST `/api/integrations/orders`

- Method: `POST`
- Authentication: `Merchant integration token`
- Required token permission: `orders.create`
- Purpose: Allows a future plugin to create an order for the merchant linked to the token.
- Rate limit: `throttle:integration-api` with the default limit sourced from `RATE_LIMIT_INTEGRATION_PER_MINUTE`.

Important behavior:

- `merchant_id` is taken from the token merchant and cannot be spoofed from request body.
- If `merchant_id` is submitted in the payload, the API returns `422`.
- `source` is stored as `integration`.
- Duplicate import is blocked for the same `merchant_id + external_source + external_order_id`.
- When shipping rate resolution fails and review fallback is enabled, the order is created with `requires_review = true` and `status = pending_review`.

Request body example:

```json
{
  "external_source": "woocommerce",
  "external_order_id": "12345",
  "external_order_number": "WC-12345",
  "order_type": "delivery",
  "customer_name": "Ahmed Adel",
  "customer_phone": "01000000000",
  "delivery_governorate_id": 1,
  "delivery_area_id": 2,
  "delivery_address": "Nasr City, Cairo",
  "payment_type": "cod",
  "cod_amount": 500,
  "fulfillment_type": "pickup_from_merchant",
  "items": [
    {
      "product_name": "T-Shirt",
      "sku": "TSHIRT-001",
      "quantity": 1,
      "unit_price": 500
    }
  ]
}
```

Validation error example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "merchant_id": [
      "The merchant id field is prohibited."
    ]
  }
}
```

Success response example:

```json
{
  "data": {
    "id": 10,
    "merchant_id": 1,
    "merchant_name": "Ahmed Store",
    "order_number": "ORD-20260523-000010",
    "customer_name": "Ahmed Adel",
    "customer_phone": "01000000000",
    "delivery_governorate_id": 1,
    "delivery_area_id": 2,
    "delivery_address": "Nasr City, Cairo",
    "cod_amount": "500.00",
    "shipping_fee": "80.00",
    "source": "integration",
    "external_source": "woocommerce",
    "external_order_id": "12345",
    "external_order_number": "WC-12345",
    "requires_review": false,
    "status": "draft",
    "items": [
      {
        "id": 20,
        "product_name": "T-Shirt",
        "sku": "TSHIRT-001",
        "quantity": 1,
        "unit_price": "500.00"
      }
    ],
    "created_at": "2026-05-23T12:30:00.000000Z"
  }
}
```

## Warehouse Enhancement APIs

### Warehouse Locations Concept

- `warehouse_zones`, `warehouse_shelves`, and `warehouse_bins` represent internal storage structure.
- Inventory stock can optionally be assigned to a `warehouse_bin_id`.
- Zone, shelf, and bin `code` values are unique inside the same warehouse.
- Bin barcodes are generated automatically if not provided.

### GET `/api/warehouse-zones`

- Method: `GET`
- Authentication: `Yes`
- Permission: `warehouse_locations.view`
- Optional filters:
- `warehouse_id`
- `status`

### POST `/api/warehouse-zones`

- Method: `POST`
- Authentication: `Yes`
- Permission: `warehouse_locations.create`

### GET `/api/warehouse-shelves`

- Method: `GET`
- Authentication: `Yes`
- Permission: `warehouse_locations.view`
- Optional filters:
- `warehouse_id`
- `zone_id`
- `status`

### POST `/api/warehouse-shelves`

- Method: `POST`
- Authentication: `Yes`
- Permission: `warehouse_locations.create`

### GET `/api/warehouse-bins`

- Method: `GET`
- Authentication: `Yes`
- Permission: `warehouse_locations.view`
- Optional filters:
- `warehouse_id`
- `zone_id`
- `shelf_id`
- `status`

Success response example:

```json
{
  "data": [
    {
      "id": 1,
      "warehouse_id": 1,
      "warehouse_name": "Main Warehouse",
      "warehouse_zone_id": 2,
      "zone_name": "A",
      "warehouse_shelf_id": 3,
      "shelf_name": "S1",
      "name": "Bin 01",
      "code": "BIN-01",
      "barcode": "BIN-BC-20260523-000001",
      "status": "active",
      "notes": null,
      "created_at": "2026-05-23T12:00:00.000000Z"
    }
  ]
}
```

### POST `/api/warehouse-bins`

- Method: `POST`
- Authentication: `Yes`
- Permission: `warehouse_locations.create`

### Picking Lists

### GET `/api/picking-lists`

- Method: `GET`
- Authentication: `Yes`
- Permission: `picking_lists.view`

### POST `/api/picking-lists/generate`

- Method: `POST`
- Authentication: `Yes`
- Permission: `picking_lists.create`
- Purpose: Generates a picking list from warehouse-fulfilled orders and links reserved or fulfilled stock items.

Request body example:

```json
{
  "warehouse_id": 1,
  "order_ids": [1, 2, 3],
  "assigned_to": 5,
  "notes": "Morning batch"
}
```

### POST `/api/picking-lists/{pickingList}/scan-pick`

- Method: `POST`
- Authentication: `Yes`
- Permission: `picking_lists.scan`
- Purpose: Scans a warehouse product barcode into the picking list and increases the picked quantity.

Request body example:

```json
{
  "barcode": "WH-PROD-1-20260523-000001",
  "quantity": 1
}
```

### POST `/api/picking-lists/{pickingList}/cancel`

- Method: `POST`
- Authentication: `Yes`
- Permission: `picking_lists.cancel`

### Packing Tasks

### GET `/api/packing-tasks`

- Method: `GET`
- Authentication: `Yes`
- Permission: `packing_tasks.view`

### POST `/api/packing-tasks/generate`

- Method: `POST`
- Authentication: `Yes`
- Permission: `packing_tasks.create`
- Purpose: Creates one packing task per order inside the picking list and skips duplicates.

Request body example:

```json
{
  "picking_list_id": 1
}
```

### POST `/api/packing-tasks/{packingTask}/complete`

- Method: `POST`
- Authentication: `Yes`
- Permission: `packing_tasks.complete`
- Purpose: Marks the packing task as packed and optionally creates a warehouse packaging charge when `packaging_fee > 0`.

Request body example:

```json
{
  "package_weight": 1.25,
  "package_length": 20,
  "package_width": 15,
  "package_height": 10,
  "packaging_fee": 15,
  "notes": "Packed with fragile box"
}
```

### Storage Fee Rules

### GET `/api/storage-fee-rules`

- Method: `GET`
- Authentication: `Yes`
- Permission: `storage_fee_rules.view`
- Optional filters:
- `merchant_id`
- `pricing_plan_id`
- `status`

### POST `/api/storage-fee-rules`

- Method: `POST`
- Authentication: `Yes`
- Permission: `storage_fee_rules.create`

### POST `/api/storage-fees/generate`

- Method: `POST`
- Authentication: `Yes`
- Permission: `storage_fees.generate`
- Purpose: Calculates merchant storage fees and creates a warehouse charge of type `storage`.

Request body example:

```json
{
  "merchant_id": 1,
  "period_start": "2026-05-01",
  "period_end": "2026-05-31"
}
```

Rule behavior:

- Merchant-specific storage fee rule is used first.
- If none exists, the system falls back to the merchant pricing plan rule.
- `per_item_per_month`: `max(total_available + total_reserved + total_damaged - free_units, 0)`
- `per_sku_per_month`: `max(active_skus_stored - free_units, 0)`

### Pick & Pack Fee Rules

### GET `/api/pick-pack-fee-rules`

- Method: `GET`
- Authentication: `Yes`
- Permission: `pick_pack_fee_rules.view`
- Optional filters:
- `merchant_id`
- `pricing_plan_id`
- `status`

### POST `/api/pick-pack-fee-rules`

- Method: `POST`
- Authentication: `Yes`
- Permission: `pick_pack_fee_rules.create`

### POST `/api/pick-pack-fees/generate`

- Method: `POST`
- Authentication: `Yes`
- Permission: `pick_pack_fees.generate`
- Purpose: Calculates and creates pick and pack warehouse charges for one order.

Request body example:

```json
{
  "order_id": 1
}
```

Formula:

- `pick_fee = first_item_fee + ((total_items - 1) * additional_item_fee)`
- `packing_fee = packing_fee_base + fragile_extra_fee` when `order.is_fragile = true`

### Warehouse KPIs

### GET `/api/warehouse/kpis`

- Method: `GET`
- Authentication: `Yes`
- Permission: `warehouse_kpis.view`
- Optional filters:
- `warehouse_id`
- `merchant_id`
- `date_from`
- `date_to`

Success response example:

```json
{
  "data": {
    "inventory": {
      "total_available": 1000,
      "total_reserved": 50,
      "total_damaged": 10,
      "total_skus": 120
    },
    "operations": {
      "picking_lists_total": 20,
      "picking_lists_completed": 15,
      "packing_tasks_total": 18,
      "packing_tasks_packed": 14
    },
    "returns": {
      "total_returns": 30,
      "sellable_returns": 20,
      "damaged_returns": 8,
      "disposed_returns": 2
    },
    "billing": {
      "storage_charges": "5000.00",
      "packaging_charges": "1500.00",
      "fulfillment_charges": "2200.00"
    }
  }
}
```
## Universal Label Printing

The backend now supports additive label rendering for warehouse products, shipments, orders, warehouse bins, and driver manifests. The backend only returns printable content. It does not attempt to print directly to a client-side printer.

### Supported Formats

- `html`
- `pdf`
- `zpl`
- `tspl`
- `json`

Format resolution rules:

- If `format` is passed in the query string or request body, it is used.
- If `format` is omitted, the system uses the `default_label_format` setting.
- Invalid formats return a standard Laravel `422` validation response.

### PDF Limitation In This Phase

No Composer PDF package was added. PDF label output uses the existing lightweight raw-PDF approach already used by invoices, adapted for fixed-size labels. That means:

- PDF labels are printable and size-aware.
- PDF labels show barcode values as text.
- HTML is preferred for browser printing.
- ZPL/TSPL are preferred for barcode-capable thermal label printers.

### New Label Endpoints

Warehouse product labels:

- `GET /api/warehouse-products/{warehouseProduct}/label`
- `POST /api/warehouse-products/labels/bulk`

Existing warehouse product barcode-label endpoints remain available and backward compatible:

- `GET /api/warehouse-products/{warehouseProduct}/barcode-label`
- `POST /api/warehouse-products/barcode-labels/bulk`

Shipment / waybill labels:

- `GET /api/shipments/{shipment}/label`
- `GET /api/shipments/{shipment}/waybill`
- `POST /api/shipments/labels/bulk`

Order labels:

- `GET /api/orders/{order}/label`

Warehouse bin labels:

- `GET /api/warehouse-bins/{warehouseBin}/label`

Generic preview / settings:

- `GET /api/labels/templates`
- `GET /api/labels/settings`
- `POST /api/labels/preview`
- `POST /api/labels/test`

Driver manifest print remains backward compatible:

- `GET /api/driver-manifests/{manifest}/print`

If no `format` is supplied on manifest print, the existing JSON response remains unchanged. If `format=html|pdf|json` is supplied, the route returns additive formatted output.

### Common Query Parameters

- `format=html|pdf|zpl|tspl|json`
- `width_mm=...`
- `height_mm=...`
- `download=1`

### Label Settings

The following settings were added under the labels group:

- `default_label_format`
- `default_label_width_mm`
- `default_label_height_mm`
- `product_label_width_mm`
- `product_label_height_mm`
- `shipment_label_width_mm`
- `shipment_label_height_mm`
- `order_label_width_mm`
- `order_label_height_mm`
- `bin_label_width_mm`
- `bin_label_height_mm`
- `printer_dpi`
- `label_margin_mm`
- `zpl_darkness`
- `zpl_speed`
- `tspl_density`
- `tspl_speed`
- `label_company_name_enabled`
- `label_logo_enabled`
- `label_qr_enabled`
- `label_barcode_human_readable`

### Example Requests

Warehouse product label:

```http
GET /api/warehouse-products/1/label?format=zpl
```

Shipment waybill:

```http
GET /api/shipments/1/waybill?format=html
```

Order label using default format:

```http
GET /api/orders/1/label
```

Bulk shipment labels:

```json
{
  "format": "tspl",
  "shipment_ids": [1, 2, 3]
}
```

Generic preview:

```json
{
  "label_type": "shipment",
  "format": "json"
}
```

## Merchant Portal Phase 1

Merchant Portal Phase 1 introduces a secure merchant-facing API layer using `merchant_users` memberships.

Core decisions:

- one merchant can have multiple portal users
- one portal user belongs to one merchant context for now
- no multi-merchant switching
- no `available_merchants`
- no switch-merchant endpoint
- merchant portal context always comes from authenticated membership

### Merchant Portal Roles

- `owner`
- `admin`
- `staff`
- `accountant`
- `warehouse_viewer`

Portal roles are separate from internal/global roles.

- Internal roles continue to use the global permission system.
- Merchant portal roles only control merchant-scoped capabilities inside `/api/merchant-portal/*`.

### Merchant Portal Statuses

- `invited`
- `active`
- `suspended`

Access rules:

- `active` membership + active merchant = allowed
- `invited`, `suspended`, soft-deleted membership = `403`
- internal user without membership = `403`
- unauthenticated request = `401`

### Merchant Membership Validation

The `merchant_users` table stores:

- `merchant_id`
- `user_id`
- `role`
- `status`
- `is_primary`
- `invited_by`
- `invited_at`
- `accepted_at`
- `last_login_at`
- timestamps
- soft deletes

Validation rules:

- merchant must exist, be active, and not be deleted
- user must exist and not be deleted
- role must be valid
- status must be valid
- same user cannot have more than one non-deleted merchant portal membership at a time
- same user cannot be linked twice to the same merchant
- soft-deleted same-merchant memberships are restored instead of duplicated
- merchant cannot have more than one active primary owner
- only active owner cannot be suspended, deleted, or demoted unless another active owner exists

### Merchant Portal Authentication

Merchant portal users use the existing Sanctum login:

- `POST /api/auth/login`

No new auth guard was introduced.

### Merchant Portal Scoping Rule

For all `/api/merchant-portal/*` routes:

- `merchant_id` in query string is rejected with `422`
- `merchant_id` in request body is rejected with `422`
- merchant context is always derived from the authenticated membership

### GET `/api/merchant-portal/me`

- Method: `GET`
- Authentication: `Yes`
- Middleware: `auth:sanctum` + merchant portal access
- Purpose: returns the authenticated merchant portal context

Success response example:

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Merchant User",
      "email": "merchant@example.com"
    },
    "merchant": {
      "id": 10,
      "name": "ABC Store",
      "company_name": "ABC Store LLC",
      "phone": "01000000000",
      "email": "ops@abcstore.com",
      "address": "Nasr City, Cairo",
      "status": "active"
    },
    "portal_role": "owner",
    "portal_status": "active",
    "capabilities": [
      "profile.view",
      "users.manage",
      "orders.view",
      "finance.view"
    ]
  }
}
```

Notes:

- returns only the current merchant
- does not return switch options
- updates `last_login_at`
- capabilities are mapped dynamically from `App\Modules\MerchantPortal\Services\MerchantPortalCapabilityService`. See [MERCHANT_PORTAL_CAPABILITY_MATRIX.md](file:///E:/logistics/logistics-frontend/docs/MERCHANT_PORTAL_CAPABILITY_MATRIX.md) for details.
- capabilities can be synced using `php artisan merchant-portal:sync-capabilities` command.

### GET `/api/merchant-portal/profile`

- Method: `GET`
- Authentication: `Yes`
- Middleware: merchant portal access
- Purpose: returns the current merchant profile in a portal-safe shape

This endpoint does not expose:

- pricing plan fields
- wallet balances
- internal notes
- internal finance configuration

### Internal Merchant Portal User Management

Permission:

- `merchant_portal_users.manage`

Endpoints:

- `GET /api/merchants/{merchant}/portal-users`
- `POST /api/merchants/{merchant}/portal-users`
- `GET /api/merchants/{merchant}/portal-users/{merchantUser}`
- `PUT /api/merchants/{merchant}/portal-users/{merchantUser}`
- `DELETE /api/merchants/{merchant}/portal-users/{merchantUser}`
- `POST /api/merchants/{merchant}/portal-users/{merchantUser}/suspend`
- `POST /api/merchants/{merchant}/portal-users/{merchantUser}/activate`

Rules:

- route merchant and merchant user must match
- inactive merchants cannot receive portal users
- `merchant_id` in payload is prohibited
- portal user creation can either link an existing user or create a new user
- new user creation validates `name`, `email`, `password`, and `password_confirmation`
- existing user linking validates `user_id`

Create payload with existing user:

```json
{
  "user_id": 25,
  "role": "staff",
  "status": "active",
  "is_primary": false
}
```

Create payload with new user:

```json
{
  "name": "Merchant Accountant",
  "email": "accounting@merchant.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "accountant",
  "status": "invited",
  "is_primary": false
}
```

### Merchant Portal Capabilities

Phase 1 capability summary:

- `owner`: full phase-1 merchant portal access
- `admin`: operational + finance + integrations
- `staff`: operational orders/shipments
- `accountant`: finance/invoices/payout request
- `warehouse_viewer`: warehouse/shipment/label visibility

## Merchant Portal Phase 2

Merchant Portal Phase 2 adds merchant-facing order and shipment APIs on top of the Phase 1 merchant context and security model.

Core security rules:

- all `/api/merchant-portal/*` routes require `auth:sanctum` and merchant portal middleware
- merchant context comes only from the authenticated `merchant_users` membership
- `merchant_id` in query string or request body is rejected with `422`
- merchant portal users can only access records that belong to their own merchant
- cross-merchant order and shipment lookups return `404`
- shipment mutation remains internal-only in this phase

### Merchant Portal Capabilities Used In Phase 2

- `orders.view`
- `orders.create`
- `orders.update`
- `shipments.view`
- `labels.print`

### Merchant Portal Orders

Endpoints:

- `GET /api/merchant-portal/orders`
- `POST /api/merchant-portal/orders`
- `GET /api/merchant-portal/orders/{order}`
- `PUT /api/merchant-portal/orders/{order}`
- `POST /api/merchant-portal/orders/{order}/confirm`
- `POST /api/merchant-portal/orders/{order}/cancel`
- `GET /api/merchant-portal/orders/{order}/label`

Important behavior:

- `merchant_id` is never accepted from merchant portal requests
- `source` is not accepted from the portal create/update payload
- because `OrderSource` currently only supports `manual` and `integration`, merchant portal orders are stored with `source = manual`
- order create reuses the existing order creation flow
- order confirm reuses the existing order confirm flow
- order update reuses the existing lifecycle safety rules
- order cancel is merchant-safe only before shipment creation and before warehouse work moves beyond releasable reservation
- if a warehouse order still has active reserved stock, the cancel flow releases the reservation before setting the order to `cancelled`

Allowed order list filters:

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

Forbidden order portal inputs:

- `merchant_id`
- `source`
- `status`
- `requires_review`
- `review_reason`
- `external_source`
- `external_order_id`
- `external_order_number`
- `warehouse_fulfillment_status`

Merchant portal order create example:

```json
{
  "customer_name": "Portal Customer",
  "customer_phone": "01050000000",
  "delivery_governorate_id": 1,
  "delivery_area_id": 2,
  "delivery_address": "Nasr City",
  "payment_type": "cod",
  "shipping_fee": 55,
  "cod_amount": 250,
  "fulfillment_type": "pickup_from_merchant",
  "items": [
    {
      "product_name": "Portal Item",
      "quantity": 2,
      "unit_price": 125
    }
  ]
}
```

Merchant portal order list item example:

```json
{
  "id": 10,
  "order_number": "ORD-20260531-000010",
  "status": "confirmed",
  "status_label": "Confirmed",
  "fulfillment_type": "pickup_from_merchant",
  "payment_type": "cod",
  "order_type": "delivery",
  "customer_name": "Portal Customer",
  "customer_phone": "01050000000",
  "delivery_location_summary": {
    "governorate_id": 1,
    "governorate_name": "Cairo",
    "area_id": 2,
    "area_name": "Nasr City",
    "address": "Nasr City"
  },
  "cod_amount": "250.00",
  "shipping_fee": "55.00",
  "total_amount": "305.00",
  "has_shipment": false,
  "shipment_summary": null,
  "warehouse_fulfillment_status": "not_required",
  "created_at": "2026-05-31T12:00:00.000000Z"
}
```

### Merchant Portal Shipments

Endpoints:

- `GET /api/merchant-portal/shipments`
- `GET /api/merchant-portal/shipments/{shipment}`
- `GET /api/merchant-portal/shipments/{shipment}/tracking`
- `GET /api/merchant-portal/shipments/{shipment}/label`
- `GET /api/merchant-portal/shipments/{shipment}/waybill`

Important behavior:

- shipments are read-only in merchant portal Phase 2
- merchant portal does not expose:
  - assign driver
  - update shipment status
  - complete delivery
  - delivery attempt creation
  - manifest closing
- shipment label and waybill reuse the existing Label module

Allowed shipment filters:

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

Forbidden shipment portal inputs:

- `merchant_id`
- `driver_id`
- `assigned_driver_id`

Merchant portal shipment list item example:

```json
{
  "id": 22,
  "shipment_number": "SHP-20260531-000022",
  "status": "out_for_delivery",
  "status_label": "Out For Delivery",
  "order_number": "ORD-20260531-000010",
  "customer_name": "Portal Customer",
  "customer_phone": "01050000000",
  "delivery_location_summary": {
    "governorate_id": 1,
    "governorate_name": "Cairo",
    "area_id": 2,
    "area_name": "Nasr City",
    "address": "Nasr City"
  },
  "cod_amount": "250.00",
  "payment_type": "cod",
  "assigned_driver_summary": {
    "id": 5,
    "name": "Driver 5"
  },
  "last_delivery_attempt_summary": null,
  "created_at": "2026-05-31T13:00:00.000000Z"
}
```

### Merchant Portal Tracking

- `GET /api/merchant-portal/shipments/{shipment}/tracking`
- authenticated merchant tracking can return more than public tracking, but it still avoids internal finance and reconciliation fields
- tracking remains scoped to the authenticated merchant only

### Merchant Portal Labels

- `GET /api/merchant-portal/orders/{order}/label`
- `GET /api/merchant-portal/shipments/{shipment}/label`
- `GET /api/merchant-portal/shipments/{shipment}/waybill`

Supported label formats remain:

- `html`
- `pdf`
- `zpl`
- `tspl`
- `json`

`labels.print` is required for merchant portal label endpoints.

### Merchant Portal Warehouse Phase 3A

All Merchant Portal warehouse endpoints:

- require `auth:sanctum`
- require active merchant portal membership through the Phase 1 middleware
- derive merchant context only from the authenticated merchant membership
- reject `merchant_id` in query string or request body with `422`

Critical stock rule:

- merchant inbound stock requests do **not** increase inventory
- merchant inbound stock requests do **not** create stock movements
- inventory increases only when internal warehouse staff confirms a real warehouse receipt

#### Merchant Portal Warehouse Products

Endpoints:

- `GET /api/merchant-portal/warehouse/products`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}`
- `GET /api/merchant-portal/warehouse/products/{warehouseProduct}/label`
- `POST /api/merchant-portal/warehouse/products/labels/bulk`

Required capabilities:

- `warehouse.view`
- `labels.print` for label endpoints

Allowed product filters:

- `search`
- `sku`
- `barcode`
- `status`
- `low_stock`
- `warehouse_id`
- `has_stock`
- `created_from`
- `created_to`

Forbidden:

- `merchant_id`

#### Merchant Portal Inventory

Endpoints:

- `GET /api/merchant-portal/warehouse/inventory`
- `GET /api/merchant-portal/warehouse/inventory/low-stock`
- `GET /api/merchant-portal/warehouse/inventory/{inventoryStock}`

Required capability:

- `warehouse.view`

Allowed inventory filters:

- `warehouse_id`
- `warehouse_product_id`
- `sku`
- `barcode`
- `low_stock`
- `has_reserved`
- `available_only`
- `search`

Forbidden:

- `merchant_id`

#### Merchant Portal Stock Movements

Endpoint:

- `GET /api/merchant-portal/warehouse/movements`

Required capability:

- `warehouse.view`

Allowed movement filters:

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

Forbidden:

- `merchant_id`

Merchant-safe movement notes:

- internal adjustment notes are not exposed through the merchant portal

#### Merchant Portal Warehouse Charges

Endpoint:

- `GET /api/merchant-portal/warehouse/charges`

Required capability:

- `warehouse.view`

Allowed charge filters:

- `date_from`
- `date_to`
- `charge_type`
- `status`
- `warehouse_id`
- `search`

Forbidden:

- `merchant_id`

Warehouse charges are read-only in Phase 3A.

#### Merchant Portal Inbound Stock Requests

Endpoints:

- `GET /api/merchant-portal/warehouse/inbound-requests`
- `POST /api/merchant-portal/warehouse/inbound-requests`
- `GET /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}`
- `POST /api/merchant-portal/warehouse/inbound-requests/{inboundRequest}/cancel`

Required capabilities:

- `warehouse.inbound_requests.view`
- `warehouse.inbound_requests.create`
- `warehouse.inbound_requests.cancel`

Allowed inbound request filters:

- `status`
- `request_number`
- `date_from`
- `date_to`
- `warehouse_id`
- `expected_arrival_from`
- `expected_arrival_to`

Forbidden:

- `merchant_id`

Inbound request create payload rules:

- `warehouse_id` is optional but must exist when provided
- `expected_arrival_date` is optional
- `items` is required and must contain at least one item
- each item must either:
  - reference an existing `warehouse_product_id` that belongs to the current merchant
  - or provide both `requested_product_name` and `requested_sku`

### Merchant Portal Finance Phase 3B

All Merchant Portal finance endpoints:

- require `auth:sanctum`
- require active merchant portal membership through the Phase 1 middleware
- derive merchant context only from the authenticated merchant membership
- reject `merchant_id` in query string or request body with `422`
- return merchant-scoped data only

Merchant finance stays read-only except for payout requests.

Merchants cannot:

- create or approve wallet transactions
- create, update, or delete invoices
- approve, reject, complete, or delete payouts
- view company profit summaries
- view reconciliation-only internal finance data

#### Merchant Portal Finance Summary

Endpoint:

- `GET /api/merchant-portal/finance/summary`

Required capability:

- `finance.view`

Allowed filters:

- `date_from`
- `date_to`

Forbidden:

- `merchant_id`

Merchant-safe response summary includes:

- `available_balance`
- `pending_balance`
- `total_cod_collected`
- `total_shipping_fees`
- `total_warehouse_charges`
- `total_payouts_pending`
- `total_payouts_completed`
- `total_invoices`
- `unpaid_invoices_count`
- `pending_payouts_count`
- `last_payout`
- `latest_invoice`

#### Merchant Portal Wallet

Endpoints:

- `GET /api/merchant-portal/finance/wallet/summary`
- `GET /api/merchant-portal/finance/wallet/transactions`
- `GET /api/merchant-portal/finance/wallet/transactions/{transaction}`

Required capability:

- `finance.view`

Allowed wallet transaction filters:

- `date_from`
- `date_to`
- `type`
- `direction`
- `status`
- `reference_type`
- `search`

Forbidden:

- `merchant_id`

Wallet routes are read-only in the merchant portal.

#### Merchant Portal Invoices

Endpoints:

- `GET /api/merchant-portal/finance/invoices`
- `GET /api/merchant-portal/finance/invoices/{invoice}`
- `GET /api/merchant-portal/finance/invoices/{invoice}/preview`
- `GET /api/merchant-portal/finance/invoices/{invoice}/download`

Required capabilities:

- `invoices.view`
- `invoices.download` for download when the role has that dedicated capability

Allowed invoice filters:

- `date_from`
- `date_to`
- `status`
- `invoice_number`
- `search`

Forbidden:

- `merchant_id`

Notes:

- invoice preview and download reuse the existing invoice services
- download remains PDF-based through the existing invoice file generation path

#### Merchant Portal Payouts

Endpoints:

- `GET /api/merchant-portal/finance/payouts`
- `GET /api/merchant-portal/finance/payouts/{payout}`
- `POST /api/merchant-portal/finance/payouts/request`

Required capabilities:

- `payouts.view`
- `payouts.request`

Allowed payout list filters:

- `date_from`
- `date_to`
- `status`
- `payout_number`
- `search`

Forbidden:

- `merchant_id`

Payout request rules:

- `amount` is required and must be positive
- payout amount must not exceed merchant available balance
- request always creates `pending` payout status
- request does not approve or complete the payout
- optional bank fields are stored as merchant-facing notes because the existing payout schema remains unchanged

Merchant portal payout requests do not create direct wallet or treasury side effects.
- duplicate `warehouse_product_id` values in the same request are rejected
- duplicate requested SKU values in the same request are rejected
- quantity must be at least `1`

### Merchant Portal Final Completion

The final Merchant Portal completion phase adds:

- `GET /api/merchant-portal/dashboard/summary`
- `GET|PUT /api/merchant-portal/profile`
- `GET|PUT /api/merchant-portal/settings`
- merchant integration token management endpoints
- merchant-scoped export endpoints
- merchant-scoped attachment endpoints
- merchant notification endpoints

Final-phase security rules:

- all routes remain under `auth:sanctum` plus merchant portal middleware
- `merchant_id` continues to be rejected in portal query/body payloads
- dashboard, integrations, exports, attachments, and notifications all resolve merchant scope from the authenticated membership only
- cross-merchant lookups return `404`

New capability areas:

- `dashboard.view`
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

See the dedicated portal guide for the full final API surface:

- `docs/merchant-portal-api-documentation.md`

Inbound request statuses used in Phase 3A:

- `submitted`
- `cancelled`
- `approved`
- `partially_received`
- `received`
- `rejected`
- `closed`

Inbound request detail example:

```json
{
  "data": {
    "id": 14,
    "request_number": "MIR-20260531-000014",
    "status": "submitted",
    "warehouse_summary": {
      "id": 2,
      "name": "Main Warehouse"
    },
    "items": [
      {
        "id": 1,
        "warehouse_product_id": 11,
        "product_name": "Blue T-Shirt",
        "sku": "TS-BLUE-001",
        "quantity": 50,
        "notes": "Existing SKU"
      },
      {
        "id": 2,
        "warehouse_product_id": null,
        "product_name": "New Product",
        "sku": "NP-001",
        "quantity": 20,
        "notes": "New SKU not yet created"
      }
    ],
    "linked_warehouse_receipt_summary": null,
    "expected_arrival_date": "2026-06-10",
    "submitted_at": "2026-05-31T14:00:00.000000Z",
    "cancelled_at": null,
    "received_at": null,
    "closed_at": null,
    "notes": "Sending goods tomorrow",
    "allowed_actions": {
      "can_cancel": true
    },
    "created_at": "2026-05-31T14:00:00.000000Z"
  }
}
```

#### Internal Merchant Inbound Review

Internal review endpoints added for operations:

- `GET /api/merchant-inbound-stock-requests`
- `GET /api/merchant-inbound-stock-requests/{inboundRequest}`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/approve`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/reject`
- `POST /api/merchant-inbound-stock-requests/{inboundRequest}/link-warehouse-receipt`

Required internal permissions:

- `merchant_inbound_requests.view`
- `merchant_inbound_requests.manage`

Linking an inbound request to a confirmed warehouse receipt can move the inbound request to:

- `received`
- `partially_received`

Linking a request to a receipt still does not replace warehouse receipt confirmation as the stock-in authority.

## Company Catalog / Packaging / Merchant Shop

### Company Catalog Concept

- `company_catalog_items` stores company-owned packaging products and service catalog items.
- This catalog is separate from merchant warehouse inventory and does not replace `warehouse_products`.
- Supported types:
  - `packaging_product`
  - `storage_service`
  - `fulfillment_service`
  - `other_service`

### Company Catalog Admin APIs

- `GET /api/company-catalog-items`
  - Permission: `company_catalog.view`
  - Filters: `type`, `category`, `status`, `is_visible_to_merchants`, `search`
- `POST /api/company-catalog-items`
  - Permission: `company_catalog.create`
- `GET /api/company-catalog-items/{companyCatalogItem}`
  - Permission: `company_catalog.view`
- `PUT /api/company-catalog-items/{companyCatalogItem}`
  - Permission: `company_catalog.update`
- `DELETE /api/company-catalog-items/{companyCatalogItem}`
  - Permission: `company_catalog.delete`

Create example:

```json
{
  "type": "packaging_product",
  "category": "flyer",
  "name": "Flyer Medium",
  "sku": "FLYER-M",
  "description": "Medium flyer bag for small orders.",
  "unit_price": 3.5,
  "currency": "EGP",
  "size_label": "Medium",
  "length": 30,
  "width": 20,
  "unit": "cm",
  "is_visible_to_merchants": true,
  "sort_order": 10,
  "status": "active"
}
```

### Order Packaging Fields

- Order create and update now accept:
  - `requires_packaging`
  - `package_notes`
  - `packaging_items`
- `packaging_items` payload example:

```json
{
  "requires_packaging": true,
  "package_notes": "Use flyer and fragile sticker",
  "packaging_items": [
    {
      "company_catalog_item_id": 1,
      "quantity": 2,
      "notes": "Use for fragile item"
    }
  ]
}
```

Rules:

- only active `packaging_product` catalog items are valid for order selection
- selections are stored in `order_packaging_items` snapshot rows
- updating catalog price later does not change existing order packaging snapshots
- additive order response fields:
  - `total_packaging_fee`
  - `packaging_items`

Packaging item response shape:

```json
{
  "id": 1,
  "company_catalog_item_id": 1,
  "item_name": "Flyer Medium",
  "item_sku": "FLYER-M",
  "category": "flyer",
  "size_label": "Medium",
  "unit_price": "3.50",
  "quantity": 2,
  "total_price": "7.00",
  "notes": "Use for fragile item"
}
```

### Merchant Shop Read-Only APIs

- `GET /api/merchant-shop/catalog`
  - Permission: `merchant_shop.view`
  - Filters: `type`, `category`, `search`
- `GET /api/merchant-shop/pricing`
  - Permission: `merchant_shop.view`
  - Optional filter: `merchant_id`
- `GET /api/merchants/{merchant}/shop-preview`
  - Permission: `merchant_shop.view`

Rules:

- read-only only
- no cart
- no checkout
- no purchase flow
- only `active` and merchant-visible catalog items are returned

Merchant pricing preview response example:

```json
{
  "data": {
    "packaging_products": [
      {
        "id": 1,
        "type": "packaging_product",
        "name": "Flyer Medium",
        "sku": "FLYER-M",
        "unit_price": "3.50",
        "currency": "EGP",
        "status": "active"
      }
    ],
    "storage_fees": [
      {
        "id": 1,
        "name": "Merchant Storage Rule",
        "calculation_type": "per_item_per_month",
        "free_units": 10,
        "unit_price": "2.50",
        "status": "active"
      }
    ],
    "pick_pack_fees": [
      {
        "id": 1,
        "name": "Merchant Pick Pack Rule",
        "pick_fee_first_item": "6.00",
        "pick_fee_additional_item": "2.00",
        "packing_fee_base": "4.00",
        "fragile_extra_fee": "3.00",
        "status": "active"
      }
    ],
    "services": [
      {
        "id": 3,
        "type": "other_service",
        "name": "Gift Wrapping Service",
        "unit_price": "0.00",
        "currency": "EGP",
        "status": "active"
      }
    ]
  }
}
```

### Permissions Added

- `company_catalog.view`
- `company_catalog.create`
- `company_catalog.update`
- `company_catalog.delete`
- `order_packaging.view`
- `order_packaging.manage`
- `merchant_shop.view`

### Settings Added

- `packaging_fee_auto_charge`
- `merchant_shop_enabled`
- `merchant_shop_show_storage_fees`
- `merchant_shop_show_pick_pack_fees`
- `merchant_shop_show_packaging_products`

### Audit Events Added

- `company_catalog_item_created`
- `company_catalog_item_updated`
- `company_catalog_item_deleted`
- `order_packaging_items_updated`
- `merchant_shop_previewed`

### Export Support Added

- export type: `company_catalog_items`
