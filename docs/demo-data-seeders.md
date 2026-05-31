# Demo Data Seeders

## Purpose

`DemoDataSeeder` creates a small, clearly marked demo dataset that is safe to remove.

It is intentionally additive:
- no schema changes
- no business logic changes
- no API contract changes
- no changes to core/system seeders

All demo records are marked with deterministic identifiers such as:
- `DEMO-ORDER-0001`
- `DEMO-SHIP-0001`
- `DEMO-WH-01`
- `DEMO-SKU-001`
- `DEMO-INV-0001`
- `DEMO-PAYOUT-0001`
- `demo.admin@example.com`
- `demo.merchant@example.com`
- `[DEMO_DATA]` notes

## Seed Command

Run manually:

```bash
php artisan db:seed --class=DemoDataSeeder
```

The seeder is idempotent. Re-running it updates or restores the same demo records instead of creating duplicates.

## Purge Command

Run manually:

```bash
php artisan demo:purge
```

Or skip confirmation:

```bash
php artisan demo:purge --force
```

`demo:purge` permanently removes only the records identified by the demo registry. It does not use broad fuzzy matching against ordinary business data.

## Demo Credentials

Internal demo user:
- email: `demo.admin@example.com`
- password: `DemoPass123!`

Merchant portal demo user:
- email: `demo.merchant@example.com`
- password: `DemoPass123!`

Seeded merchant record:
- email: `demo.ops@example.com`
- name: `DEMO Merchant`

## Demo Records Created

The seeded dataset includes:
- 1 internal demo user
- 1 merchant portal demo user
- 1 demo merchant
- 1 demo governorate and 1 demo area
- 1 demo warehouse, zone, shelf, and bin
- 1 demo driver
- 1 demo warehouse product and inventory stock row
- 1 demo merchant portal membership and portal settings row
- 1 demo order with 1 order item
- 1 demo shipment with 3 status history events
- 1 demo stock movement
- 1 demo merchant wallet transaction
- 1 demo merchant invoice
- 1 demo merchant payout

## Purge Strategy

`demo:purge` removes demo data by exact known identifiers from `App\Support\DemoDataRegistry`, including:
- demo emails
- demo merchant email
- demo warehouse/location codes
- demo SKU and barcode values
- demo order/shipment/invoice/reference numbers
- `[DEMO_DATA]` notes where needed for child records

Deletion is done in dependency order so child rows are removed before parents when appropriate. Soft-deleted demo rows are force-deleted during purge.

## Notes

- This seeder assumes the application schema is already migrated.
- For the best permission-aware login experience, the standard role/permission seeders should already exist in the environment.
- Demo integration tokens are intentionally not seeded because plain tokens should not be recoverable from stored hashes.
