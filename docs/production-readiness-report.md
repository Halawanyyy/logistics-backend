# Production Readiness Report

## 1. Current Production Readiness Summary

The backend is strong on route protection, merchant isolation, and core business-flow safety, but it was not fully production-hardened at the configuration layer before this pass.

Safe hardening implemented in this pass:

- added explicit `config/cors.php`
- made application timezone env-driven through `APP_TIMEZONE`
- made private storage roots env-overridable while keeping the current defaults
- added conservative named throttles for login, public tracking, and integration-token routes
- expanded `.env.example` with production-sensitive variables
- added `tests/Feature/ProductionReadinessTest.php`

Current status after these changes:

- application security posture: good
- production configuration posture: improved, but still requires operator setup
- deployment readiness: reasonable after manual verification and environment review

## 2. Missing `.env.example` Variables That Were Addressed

This pass added the main missing variables that affected production setup clarity:

- `APP_TIMEZONE`
- `APP_PREVIOUS_KEYS`
- `FRONTEND_URL`
- `ADMIN_FRONTEND_URL`
- `MERCHANT_PORTAL_URL`
- `SESSION_SECURE_COOKIE`
- `SESSION_SAME_SITE`
- `SESSION_PARTITIONED_COOKIE`
- `SANCTUM_STATEFUL_DOMAINS`
- `CORS_ALLOWED_ORIGINS`
- `CORS_ALLOWED_ORIGIN_PATTERNS`
- `CORS_SUPPORTS_CREDENTIALS`
- `CORS_MAX_AGE`
- `FILESYSTEM_PRIVATE_ROOT`
- `FILESYSTEM_EXPORTS_ROOT`
- `FILESYSTEM_ATTACHMENTS_ROOT`
- `QUEUE_FAILED_DRIVER`
- `QUEUE_FAILED_TABLE`
- `LOG_DAILY_DAYS`
- `RATE_LIMIT_AUTH_LOGIN_PER_MINUTE`
- `RATE_LIMIT_PUBLIC_TRACKING_PER_MINUTE`
- `RATE_LIMIT_INTEGRATION_PER_MINUTE`
- `AWS_URL`
- `AWS_ENDPOINT`

## 3. Storage And Private File Risks

Observed storage behavior:

- internal private files use `storage/app/private`
- attachments use `storage/app/attachments`
- exports use `storage/app/exports`
- invoice PDFs are generated under the private `local` disk path

Risk assessment:

- files are controller-gated today, which is good
- deployment still needs private-disk backup coverage and retention policy
- storage exhaustion is a non-blocking operational risk if exports and generated PDFs accumulate
- raw `file_path` exposure has now been removed from the reviewed attachment, export, and invoice API resources

## 4. Queue And Scheduler Risks

Findings from this pass:

- no custom scheduled tasks were found
- queue configuration exists, but obvious queue-heavy workflows were not found in the reviewed modules
- exports, invoices, and portal notifications appear largely synchronous in the current implementation

Risks:

- synchronous heavy work can become a latency risk under production load
- if queues are enabled later, worker supervision and failed-job monitoring become required
- the lack of scheduled cleanup means old exports and generated files may accumulate unless handled externally

## 5. CORS And Sanctum Risks

Before this pass:

- CORS middleware was globally present
- no explicit `config/cors.php` file was published
- `.env.example` did not surface key Sanctum/session variables

After this pass:

- `config/cors.php` now explicitly covers `api/*` and `sanctum/csrf-cookie`
- allowed origins are env-driven
- credential support is env-driven and defaults to `false`

Remaining risks:

- if a browser SPA uses Sanctum cookies, operators must set exact `SANCTUM_STATEFUL_DOMAINS`
- cross-site cookie auth also requires `SESSION_SECURE_COOKIE=true` and usually `SESSION_SAME_SITE=none`
- if the app stays bearer-token only, those cookie-specific settings are less critical but the origins still must be reviewed

## 6. Rate Limiting Risks

Before this pass:

- no explicit route throttling was found for login, public tracking, or integration-token traffic

After this pass:

- `POST /api/auth/login` uses `throttle:auth-login`
- `GET /api/track/{shipment_number}` uses `throttle:public-tracking`
- `GET /api/integrations/me` and `POST /api/integrations/orders` use `throttle:integration-api`

Remaining considerations:

- the current defaults are intentionally conservative and should be tuned to real traffic
- public tracking may still benefit from CDN or edge throttling if exposed broadly
- integration limits should be reviewed against merchant plugin polling/import patterns

## 7. Logging Risks

Findings:

- logging configuration is standard Laravel and functional
- `.env.example` now includes `LOG_DAILY_DAYS`, but runtime logging strategy still depends on deployment env values

Remaining risks:

- leaving production on noisy `debug`-style settings increases log volume
- local-file logging without rotation or centralized shipping can create retention and disk-pressure issues
- alerting for 5xx, auth failures, and queue failures is still an operational responsibility outside the codebase

## 8. Backup And Deployment Notes

Deployment notes:

- config files changed in this pass, so cache clearing is required after deployment
- verify write permissions for `storage/` and `bootstrap/cache/`
- ensure private file directories are included in backup plans when business-critical

Backup notes:

- database backup remains mandatory
- backup `storage/app/private`, `storage/app/attachments`, and `storage/app/exports` when those artifacts are required for audit, customer service, or finance workflows
- if generated invoices are legally or financially important, confirm restoration expectations for those files

## 9. Recommended Safe Changes

Completed in this pass:

- publish explicit CORS configuration
- make timezone configurable through env
- make private storage roots configurable through env while preserving existing defaults
- add named throttles for login, public tracking, and integration-token endpoints
- expand `.env.example`
- add a focused readiness regression test

Suggested later, but not changed here:

- review whether exports and invoice generation should be queued
- add scheduled cleanup for old export artifacts if retention policy allows it
- consider centralized logging or daily rotation as the default production standard

## 10. Files Likely To Be Changed In This Pass

- `.env.example`
- `config/app.php`
- `config/filesystems.php`
- `config/cors.php`
- `app/Providers/AppServiceProvider.php`
- `app/Modules/Auth/Routes/api.php`
- `app/Modules/Shipment/Routes/api.php`
- `app/Modules/MerchantIntegration/Routes/api.php`
- `tests/Feature/ProductionReadinessTest.php`
- `docs/production-readiness-checklist.md`
- `docs/production-readiness-report.md`
- `docs/api-documentation.md`
- `docs/api-endpoints-list.md`

## 11. Manual Commands To Run

Run these manually after review:

```bash
php artisan optimize:clear
php artisan test --filter=ProductionReadinessTest
php artisan test
```
