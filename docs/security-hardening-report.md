# Security Hardening Report

## 1. Summary

This pass focused on backend security hardening without changing the existing architecture or business flows.

Implemented in this pass:

- verified public route surface remains intentionally small
- verified public tracking remains redacted and throttled
- kept integration merchant spoofing rejection in place
- added explicit stored-path safety checks for attachment, export, and invoice downloads
- removed raw internal `file_path` exposure from authenticated attachment, export, and invoice resources
- added missing audit coverage for merchant portal integration token lifecycle actions
- added missing audit coverage for payout creation
- added `tests/Feature/SecurityHardeningTest.php`

## 2. Auth Hardening Status

- login is throttled through `throttle:auth-login`
- invalid login remains generic and does not distinguish email vs. password failure
- soft-deleted users are excluded from login through the `User` soft-delete scope
- logout revokes only the current access token
- `/api/auth/me` does not expose password, remember token, or token collections
- internal inactive or suspended user enforcement was not added because no internal user status field was found in this pass

## 3. Token Security Status

- Sanctum raw token is returned only on login
- integration raw token is returned only once on creation
- integration tokens are stored as `token_hash`, not plaintext
- revoked integration tokens do not authenticate
- expired integration tokens do not authenticate
- integration merchant context is derived from the token
- integration payload `merchant_id` remains prohibited

## 4. Route Security Status

Public routes reviewed and intentionally limited to:

- `POST /api/auth/login`
- `GET /api/auth/ping`
- `GET /api/track/{shipment_number}`
- `GET /api/public/settings`

Sensitive internal routes remain behind `auth:sanctum` and route-level permissions.

Merchant routes remain behind merchant portal middleware and owned-resource lookups.

Integration routes remain behind the integration token middleware and integration throttle.

## 5. Permission Hardening Status

- reviewed internal finance, warehouse, export, attachment, settings, audit, label, shipment, and user-management route groups
- route-level permission coverage remains strong overall
- no new permission model was introduced
- no obviously public sensitive admin routes were found in the reviewed route files

## 6. Merchant Isolation Status

- merchant portal still rejects `merchant_id` in query or body
- merchant portal still derives merchant context from the active membership
- integration APIs still derive merchant context from the token
- merchant-scoped exports, attachments, invoices, payouts, wallet data, and notifications remain restricted to the owning merchant

## 7. Public Tracking Redaction Status

- public tracking remains public and rate-limited
- customer name, full address, raw notes, merchant internals, and driver internals remain excluded
- safe progress fields and redacted timeline fields remain available

## 8. Download And File Security Status

Hardened in this pass:

- internal attachment, export, and invoice resources no longer expose raw `file_path`
- attachment download paths now reject malformed or traversal-style stored values
- export download paths now reject malformed or traversal-style stored values
- invoice download paths now reject malformed or traversal-style stored values outside the expected private invoice directory

Merchant portal download scoping remained unchanged and safe.

## 9. Sensitive Data Exposure Status

- no password hashes or raw integration token hashes are exposed in the reviewed API responses
- public and merchant portal responses continue to avoid raw file paths
- internal authenticated attachment, export, and invoice resources now also avoid raw file paths

## 10. Rate Limiting Status

Present and verified:

- `POST /api/auth/login` -> `throttle:auth-login`
- `GET /api/track/{shipment_number}` -> `throttle:public-tracking`
- `/api/integrations/*` -> `throttle:integration-api`

Still recommended later:

- export generation throttling
- attachment upload throttling
- bulk label throttling
- payout request throttling
- notification mark-all-read throttling

## 11. Audit Logging Status

Already present before this pass:

- settings update and bulk update
- payout approve, reject, and complete
- attachment upload and delete
- export generation
- internal integration token create, revoke, and delete

Added in this pass:

- merchant portal integration token create audit
- merchant portal integration token revoke audit
- merchant portal integration token delete audit
- payout creation audit for internal flow
- payout creation audit for merchant portal request flow

Still recommended later:

- download-action auditing for exports, attachments, and invoices if operationally needed

## 12. Validation Hardening Status

- integration `merchant_id` prohibition remains enforced
- merchant portal `merchant_id` prohibition remains enforced
- merchant portal export and attachment requests remain scoped and validated
- no broad validation rewrite was necessary for this pass

## 13. Tests Added Or Updated

Added:

- `tests/Feature/SecurityHardeningTest.php`

Coverage includes:

- deleted user login rejection
- auth response sensitive-field checks
- throttle middleware presence on login, public tracking, and integration routes
- one-time integration raw token exposure
- internal file-path redaction for attachments, exports, and invoices
- malicious stored download path rejection
- audit logging for portal integration token lifecycle and payout creation

## 14. Fixes Implemented

- removed internal `file_path` exposure from `AttachmentResource`
- removed internal `file_path` exposure from `ExportLogResource`
- removed internal `file_path` exposure from `MerchantInvoiceResource`
- added traversal-resistant stored path validation for attachments
- added traversal-resistant stored path validation for exports
- added expected-directory validation for invoice downloads
- added audit logging for merchant portal integration token create, revoke, and delete
- added audit logging for payout creation

## 15. Remaining Non-Blocking Security Recommendations

- consider curating `/api/auth/me` behind a dedicated resource if you want stricter response uniformity
- consider route throttles for export creation, attachment upload, bulk labels, payout requests, and notification mass actions
- consider auditing sensitive file downloads if your compliance posture requires it
- consider more granular internal attachment authorization tied to the linked resource, not only the coarse attachment permission

## 16. Manual Commands To Run

Run these manually after review:

```bash
php artisan optimize:clear
php artisan test --filter=SecurityHardeningTest
php artisan test
```
