# Testing Guide

## Overview

- Feature tests live under `tests/Feature`.
- The project is already configured for automated tests through `php artisan test`.
- Current default test configuration in `phpunit.xml` uses in-memory SQLite.
- If your team later switches testing to MySQL, keep the same feature tests and update only the test environment variables.

## Configure Test Database

### Default setup

- No extra setup is required for the current default test database.
- `phpunit.xml` already sets:
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

### Optional MySQL setup

- If you want to run tests on MySQL instead:
- Create a dedicated empty test database.
- Set test environment values before running tests, for example:

```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=logistics_backend_test
DB_USERNAME=root
DB_PASSWORD=
```

- Keep the database isolated from local development data.

## Run Tests

### Run all tests

```bash
php artisan test
```

### Run one test class

```bash
php artisan test --filter=AuthTest
```

### Run one specific test method

```bash
php artisan test --filter=test_upload_and_download_attachment_and_generate_export
```

## Current Feature Test Coverage

- `AuthTest`
- `OrderShipmentFlowTest`
- `WarehouseFlowTest`
- `DriverManifestFlowTest`
- `FinanceWalletFlowTest`
- `SettingsAuditTest`
- `AttachmentsExportTest`
- `MerchantIntegrationTokenTest`

## Expected Pass Criteria

- All feature tests pass with no failed assertions.
- Migrations run automatically in the test process.
- Permission-protected endpoints are exercised with seeded roles and permissions.
- Export and attachment tests generate and read files through Laravel storage fakes.
- Wallet, shipment, warehouse, manifest, and settings flows keep their backward-compatible responses.

## Recommended Command Order

```bash
php artisan migrate
php artisan db:seed --class=SystemSettingSeeder
php artisan db:seed --class=RolePermissionSeeder
php artisan test
```
