# Merchant Portal Backend Seeding & Capabilities Sync

This document details the seeding process and command-line synchronization utility for the Merchant Portal capabilities.

## Role-to-Capability Matrix Baselines
Capabilities are dynamically computed for merchant users based on their assigned role in the `merchant_users` pivot/membership table. The mappings are implemented in:
* `App\Modules\MerchantPortal\Services\MerchantPortalCapabilityService`

### Available Roles
* `owner`
* `admin`
* `staff`
* `accountant`
* `warehouse_viewer`
* `viewer`

---

## Idempotent Seeder
The seeder creates standard system roles and configures the default demo accounts for local and testing environments without affecting production data.

* **Seeder Class**: `Database\Seeders\MerchantPortalCapabilitiesSeeder`
* **Command to run**:
  ```bash
  php artisan db:seed --class=MerchantPortalCapabilitiesSeeder
  ```

### Demo Accounts Seeded
The following accounts are registered under the `merchant_portal` role and connected to the default active merchant:
* **Owner**: `merchant.owner@example.com` (password: `secret123`)
* **Staff**: `merchant.staff@example.com` (password: `secret123`)
* **Accountant**: `merchant.accountant@example.com` (password: `secret123`)
* **Warehouse Viewer**: `merchant.warehouse@example.com` (password: `secret123`)
* **Viewer**: `merchant.viewer@example.com` (password: `secret123`)

---

## Capabilities Synchronization CLI Command
We provide an artisan command to audit, log, and re-sync capabilities of existing portal users.

* **Artisan Command**:
  ```bash
  php artisan merchant-portal:sync-capabilities
  ```

### Signature & Arguments
```bash
merchant-portal:sync-capabilities
  {--strict : Strict sync mode (unsupported for hardcoded setup, but kept for signature)}
  {--dry-run : Dry run mode, prints what would change without modifying}
  {--merchant-id= : Sync only a specific merchant by ID}
  {--role= : Sync only users of a specific portal role}
  {--create-missing-owners : Create missing owner portal accounts for active merchants}
```

### Examples

**Audit capabilities without modifying the database (Dry-Run)**:
```bash
php artisan merchant-portal:sync-capabilities --dry-run
```

**Generate missing owner accounts for all active merchants who don't have one**:
```bash
php artisan merchant-portal:sync-capabilities --create-missing-owners
```

**Force synchronization of a specific merchant's portal accounts**:
```bash
php artisan merchant-portal:sync-capabilities --merchant-id=1
```
