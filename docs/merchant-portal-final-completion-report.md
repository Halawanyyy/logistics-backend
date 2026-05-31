# Merchant Portal Final Completion Report

## Summary

The final Merchant Portal completion phase adds the remaining merchant-facing APIs for:

- dashboard summary
- profile and portal settings
- merchant integration token management
- merchant-scoped exports
- merchant-scoped attachments
- merchant notifications

This phase keeps the Phase 1 security model intact:

- `auth:sanctum` is still used
- active merchant portal membership is still required
- merchant context still comes only from the authenticated membership
- `merchant_id` is rejected across `/api/merchant-portal/*`

## New APIs

### Dashboard

- `GET /api/merchant-portal/dashboard/summary`

### Profile And Settings

- `GET /api/merchant-portal/profile`
- `PUT /api/merchant-portal/profile`
- `GET /api/merchant-portal/settings`
- `PUT /api/merchant-portal/settings`

### Integration Tokens

- `GET /api/merchant-portal/integration-tokens`
- `POST /api/merchant-portal/integration-tokens`
- `GET /api/merchant-portal/integration-tokens/{token}`
- `POST /api/merchant-portal/integration-tokens/{token}/revoke`
- `DELETE /api/merchant-portal/integration-tokens/{token}`
- `GET /api/merchant-portal/integrations/docs`
- `POST /api/merchant-portal/integrations/test`

### Exports

- `GET /api/merchant-portal/exports/types`
- `POST /api/merchant-portal/exports`
- `GET /api/merchant-portal/exports`
- `GET /api/merchant-portal/exports/{export}`
- `GET /api/merchant-portal/exports/{export}/download`

### Attachments

- `GET /api/merchant-portal/attachments`
- `POST /api/merchant-portal/attachments`
- `GET /api/merchant-portal/attachments/{attachment}`
- `GET /api/merchant-portal/attachments/{attachment}/download`
- `DELETE /api/merchant-portal/attachments/{attachment}`

### Notifications

- `GET /api/merchant-portal/notifications`
- `GET /api/merchant-portal/notifications/unread-count`
- `POST /api/merchant-portal/notifications/{notification}/mark-read`
- `POST /api/merchant-portal/notifications/mark-all-read`

## Security And Scoping Rules

- all final-phase endpoints require `auth:sanctum` and merchant portal middleware
- all data is scoped by the authenticated merchant membership
- `merchant_id` in query string or request body returns `422`
- cross-merchant resource lookups return `404`
- internal-only fields are not exposed in portal resources
- portal users still cannot perform internal/admin-only finance, warehouse, or shipment operations

## Capability Additions

Added or expanded merchant portal capabilities:

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

## Migrations Added

- `2026_05_31_200001_add_merchant_scope_to_export_logs_and_attachments.php`
- `2026_05_31_200002_create_merchant_portal_settings_table.php`
- `2026_05_31_200003_create_merchant_notifications_table.php`

## Files Added

- merchant portal final controllers, requests, resources, and services for dashboard, settings, integrations, exports, attachments, and notifications
- `tests/Feature/MerchantPortalFinalCompletionTest.php`
- `docs/merchant-portal-api-documentation.md`
- `docs/merchant-portal-final-completion-report.md`

## Files Modified

- `app/Models/Merchant.php`
- `app/Modules/Attachment/Models/Attachment.php`
- `app/Modules/Attachment/Services/AttachmentService.php`
- `app/Modules/Export/Models/ExportLog.php`
- `app/Modules/Export/Services/ExportService.php`
- `app/Modules/MerchantIntegration/Models/MerchantIntegrationToken.php`
- `app/Modules/MerchantPortal/Controllers/MerchantPortalProfileController.php`
- `app/Modules/MerchantPortal/Routes/api.php`
- `app/Modules/MerchantPortal/Services/MerchantPortalCapabilityService.php`
- `config/attachments.php`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`
- `tests/Feature/Concerns/InteractsWithPlatform.php`

## Tests Added

- `MerchantPortalFinalCompletionTest`

The final regression suite covers:

- dashboard scoping
- profile and settings safety
- integration token ownership and raw-token behavior
- export scoping and merchant-safe download
- attachment scoping and safe download
- notification ownership and mark-read behavior
- capability enforcement
- internal API regression smoke checks

## Manual Commands To Run

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan test --filter=MerchantPortalFinalCompletionTest
php artisan test --filter=MerchantPortalPhaseOneTest
php artisan test --filter=MerchantPortalPhaseTwoTest
php artisan test --filter=MerchantPortalPhaseThreeWarehouseTest
php artisan test --filter=MerchantPortalPhaseThreeFinanceTest
php artisan test --filter=MerchantIntegrationTokenTest
php artisan test --filter=AttachmentsExportTest
php artisan test
```

## Limitations And Follow-Up Notes

- merchant notifications are API-only in this phase; no email, SMS, or WhatsApp delivery was added
- attachment visibility now supports both direct merchant ownership and linked-resource ownership, but older records without merchant metadata still rely on linked resource scoping
- portal exports remain CSV-only because the existing export system is CSV-based
- integration test/docs endpoints are informational and validation-focused only; they do not execute external network calls

## Final Status

This phase completes the remaining merchant portal backend surface while preserving the earlier phase boundaries:

- Phase 1 security and scoping remain the foundation
- Phase 2 orders and shipments remain merchant-scoped
- Phase 3A warehouse remains read-only except inbound request creation/cancel
- Phase 3B finance remains read-only except payout requests
- final completion adds only merchant-safe dashboard, profile/settings, integrations, exports, attachments, and notifications
